<?php
session_start();

class User {
    private $username, $password;

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }

    public function authenticate($password) {
        return password_verify($password, $this->password);
    }

    public function getUsername() {
        return $this->username;
    }

    public static function loadUsers($jsonFile) {
        return file_exists($jsonFile)
            ? array_map(fn($u) => new User($u['username'], $u['password']), json_decode(file_get_contents($jsonFile), true))
            : [];
    }

    public static function saveUser($username, $password, $jsonFile) {
        $users = self::loadUsers($jsonFile);
        $users[] = new User($username, $password);
        file_put_contents($jsonFile, json_encode($users, JSON_PRETTY_PRINT));
    }
}

class Task {
    private $id, $title, $description, $status, $priority;

    public function __construct($id, $title, $description, $status, $priority) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->priority = $priority;
    }

    public function getId() { return $this->id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getPriority() { return $this->priority; }
    public function toggleStatus() { $this->status = !$this->status; }
}

class TodoList {
    private $tasks = [];

    public function loadTasks($jsonFile) {
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            $this->tasks = array_map(fn($t) => new Task($t['id'], $t['title'], $t['description'], $t['status'], $t['priority']), $data);
        }
    }

    public function addTask($title, $description, $priority) {
        $this->tasks[] = new Task(count($this->tasks) + 1, $title, $description, false, $priority);
        $this->saveTasksToFile('tasks.json');
    }

    public function updateTaskStatus($taskIds) {
        foreach ($this->tasks as $task) {
            if (in_array($task->getId(), $taskIds)) {
                $task->toggleStatus();
            }
        }
        $this->saveTasksToFile('tasks.json');
    }

    public function getTasks() {
        return $this->tasks;
    }

    public function searchTasks($query) {
        return array_filter($this->tasks, fn($t) => stripos($t->getTitle(), $query) !== false || stripos($t->getDescription(), $query) !== false);
    }

    public function filterByPriority($priority) {
        return array_filter($this->tasks, fn($t) => $t->getPriority() === $priority);
    }

    private function saveTasksToFile($jsonFile) {
        file_put_contents($jsonFile, json_encode(array_map(fn($t) => [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'description' => $t->getDescription(),
            'status' => $t->getStatus(),
            'priority' => $t->getPriority()
        ], $this->tasks), JSON_PRETTY_PRINT));
    }
}

$todolist = new TodoList();
$todolist->loadTasks('tasks.json');

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    User::saveUser($username, $password, 'users.json');
}

if (isset($_POST['login'])) {
    $users = User::loadUsers('users.json');
    foreach ($users as $user) {
        if ($user->getUsername() === $_POST['username'] && $user->authenticate($_POST['password'])) {
            $_SESSION['username'] = $user->getUsername();
            break;
        }
    }
}

if (isset($_POST['add_task'])) {
    $todolist->addTask($_POST['title'], $_POST['description'], $_POST['priority']);
}

if (isset($_POST['update_status']) && isset($_POST['task_ids'])) {
    $todolist->updateTaskStatus($_POST['task_ids']);
}

$tasks = $todolist->getTasks();

if (isset($_POST['search_query'])) {
    $tasks = $todolist->searchTasks($_POST['search_query']);
}

if (isset($_POST['filter_priority']) && $_POST['filter_priority'] !== '') {
    $tasks = $todolist->filterByPriority($_POST['filter_priority']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
        }
        table {
            width: 100%; 
            border-collapse: collapse; 
        }
        th, td {
            padding: 10px; 
            border: 1px solid #ccc; 
        }
        th {
            background-color: mediumaquamarine; 
        }
        .completed {
            text-decoration: line-through; 
            color: #aaa;
        }
    </style>
</head>
<body>
    <h1>Quản lý</h1>

    <?php if (!isset($_SESSION['username'])): ?>
    <form method="POST">
        <h2>Đăng ký</h2>
        <input type="text" name="username" placeholder="Tên người dùng" required>
        <input type="password" name="password" placeholder="Mật khẩu" required>
        <button type="submit" name="register">Đăng ký</button>
    </form>

    <form method="POST">
        <h2>Đăng nhập</h2>
        <input type="text" name="username" placeholder="Tên người dùng" required>
        <input type="password" name="password" placeholder="Mật khẩu" required>
        <button type="submit" name="login">Đăng nhập</button>
    </form>
    <?php else: ?>
        <h2>Chào, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
        <form method="POST">
            <input type="text" name="title" placeholder="Tiêu đề" required>
            <br>
            <textarea name="description" placeholder="Mô tả"></textarea>
            <select name="priority">
                <option value="low">Thấp</option>
                <option value="medium">Trung bình</option>
                <option value="hard">Cao</option>
            </select>
            <button type="submit" name="add_task">Thêm công việc</button>
        </form>

        <form method="POST">
            <input type="text" name="search_query" placeholder="Tìm kiếm công việc...">
            <button type="submit">Tìm kiếm</button>
            <select name="filter_priority">
                <option value="">-- Lọc theo mức độ ưu tiên --</option>
                <option value="low">Thấp</option>
                <option value="medium">Trung bình</option>
                <option value="hard">Cao</option>
            </select>
            <button type="submit">Lọc</button>
        </form>

        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Chọn</th>
                        <th>Tiêu đề</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th>Mức độ ưu tiên</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><input type="checkbox" name="task_ids[]" value="<?= $task->getId() ?>" <?= $task->getStatus() ? 'checked' : '' ?>></td>
                        <td class="<?= $task->getStatus() ? 'completed' : '' ?>"><?= htmlspecialchars($task->getTitle()) ?></td>
                        <td class="<?= $task->getStatus() ? 'completed' : '' ?>"><?= htmlspecialchars($task->getDescription()) ?></td>
                        <td><?= $task->getStatus() ? 'Hoàn thành' : 'Chưa hoàn thành' ?></td>
                        <td><?= ucfirst($task->getPriority()) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="update_status">Cập nhật trạng thái</button>
        </form>
    <?php endif; ?>
</body>
</html>