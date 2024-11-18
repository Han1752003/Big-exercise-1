<?php
session_start();

class User {
    private $username;
    private $password;
    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }
    public function getUsername() {
        return $this->username;
    }
    public static function register($username, $password) {
        $users = json_decode(file_get_contents('users.json'), true) ?? [];
        if (isset($users[$username])) {
            return false;
        }
        $users[$username] = (new User($username, $password))->password;
        file_put_contents('users.json', json_encode($users));
        return true;
    }
    public static function login($username, $password) {
        $users = json_decode(file_get_contents('users.json'), true);
        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            return true;
        }
        return false;
    }
}

class Task {
    private $title;
    private $status;
    private $content;

    public function __construct($title, $status, $content) {
        $this->title = $title;
        $this->status = $status;
        $this->content = $content;
    }
    public function getTitle() {
        return $this->title;
    }
    public function getStatus() {
        return $this->status;
    }
    public function getContent() {
        return $this->content;
    }
    public function setTitle($title) {
        $this->title = $title;
    }
    public function setStatus($status) {
        $this->status = $status;
    }
    public function setContent($content) {
        $this->content = $content;
    }
}

class TodoList {
    private $tasks = [];

    public function __construct($username) {
        $this->loadTasks($username);
    }

    private function loadTasks($username) {
        $todos = json_decode(file_get_contents('todos.json'), true) ?? [];
        if (isset($todos[$username])) {
            foreach ($todos[$username] as $taskData) {
                $this->tasks[] = new Task($taskData['title'], $taskData['status'], $taskData['content']);
            }
        }
    }

    public function addTask($title, $status, $content, $username) {
        $task = new Task($title, $status, $content);
        $this->tasks[] = $task;
        $this->saveTasks($username);
    }

    public function editTask($index, $title, $status, $content, $username) {
        if (isset($this->tasks[$index])) {
            $this->tasks[$index]->setTitle($title);
            $this->tasks[$index]->setStatus($status);
            $this->tasks[$index]->setContent($content);
            $this->saveTasks($username);
        }
    }

    public function deleteTask($index, $username) {
        if (isset($this->tasks[$index])) {
            unset($this->tasks[$index]);
            $this->tasks = array_values($this->tasks);
            $this->saveTasks($username);
        }
    }

    private function saveTasks($username) {
        $todos = json_decode(file_get_contents('todos.json'), true) ?? [];
        $todos[$username] = []; // Reset tasks for the user
        foreach ($this->tasks as $task) {
            $todos[$username][] = [
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'content' => $task->getContent()
            ];
        }
        file_put_contents('todos.json', json_encode($todos));
    }

    public function getTasks() {
        return $this->tasks;
    }
}

if (isset($_POST['register'])) {
    User::register($_POST['username'], $_POST['password']);
}

if (isset($_POST['login'])) {
    if (User::login($_POST['username'], $_POST['password'])) {
        $_SESSION['username'] = $_POST['username'];
    }
}
if (isset($_SESSION['username'])) {
    $todoList = new TodoList($_SESSION['username']);
    if (isset($_POST['add_task'])) {
        $todoList->addTask($_POST['title'], 'incomplete', $_POST['content'], $_SESSION['username']);
    }
    if (isset($_POST['edit_task'])) {
        $todoList->editTask($_POST['task_index'], $_POST['title'], $_POST['status'], $_POST['content'], $_SESSION['username']);
    }
    if (isset($_POST['delete_task'])) {
        $todoList->deleteTask($_POST['task_index'], $_SESSION['username']);
    }
    $tasks = $todoList->getTasks();
} else {
    $tasks = [];
}
$editTaskIndex = null;
$editTask = null;
if (isset($_POST['edit'])) {
    $editTaskIndex = $_POST['task_index'];
    $editTask = $tasks[$editTaskIndex];
}
if (isset($_POST['delete_task']) || isset($_POST['edit_task'])) {
    $editTaskIndex = null;
    $editTask = null;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thông tin</title>
</head>
<body>
    <h1>Quản lý thông tin</h1>
    <?php if (!isset($_SESSION['username'])): ?>
        <form method="post">
            <h2>Đăng ký</h2>
            <input type="text" name="username" required placeholder="Tên người dùng">
            <input type="password" name="password" required placeholder="Mật khẩu">
            <button type="submit" name="register">Đăng ký</button>
        </form>
        <form method="post">
            <h2>Đăng nhập</h2>
            <input type="text" name="username" required placeholder="Tên người dùng">
            <input type="password" name="password" required placeholder="Mật khẩu">
            <button type="submit" name="login">Đăng nhập</button>
        </form>
    <?php else: ?>
        <h2>Xin chào, <?php echo $_SESSION['username']; ?></h2>
        <form method="post">
            <input type="text" name="title" required placeholder="Tiêu đề công việc" value="<?php echo $editTask ? $editTask->getTitle() : ''; ?>">
            <br>
            <textarea name="content" placeholder="Nội dung công việc"><?php echo $editTask ? $editTask->getContent() : ''; ?></textarea>
            <br>
            <input type="hidden" name="task_index" value="<?php echo $editTaskIndex; ?>">
            <input type="hidden" name="status" value="<?php echo $editTask ? $editTask->getStatus() : 'incomplete'; ?>">
            <button type="submit" name="<?php echo $editTask ? 'edit_task' : 'add_task'; ?>">
                <?php echo $editTask ? 'Cập nhật công việc' : 'Thêm công việc'; ?>
            </button>
        </form>
        <ul>
            <?php foreach ($tasks as $index => $task): ?>
                <li>
                    <?php echo $task->getTitle(); ?> - <?php echo $task->getStatus(); ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="task_index" value="<?php echo $index; ?>">
                        <button type="submit" name="delete_task">Xóa</button>
                        <button type="submit" name="edit" style="margin-left: 5px;">Sửa</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>