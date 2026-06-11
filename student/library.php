<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Library';
$db = getDB();
$userId = $_SESSION['user_id'];

$search = sanitizeInput($_GET['search'] ?? '');
$books = [];

$sql = "SELECT * FROM books WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " ORDER BY title LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$stmt = $db->prepare("SELECT b.*, br.borrow_date, br.due_date, br.status FROM borrowings br JOIN books b ON br.book_id = b.id WHERE br.user_id = ? AND br.status = 'borrowed'");
$stmt->execute([$userId]);
$borrowed = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-book-open me-2"></i>Library</h4>
</div>

<?php if (!empty($borrowed)): ?>
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">Currently Borrowed Books</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Title</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($borrowed as $b): ?>
                    <tr>
                        <td><?= sanitizeInput($b['title']) ?></td>
                        <td><?= sanitizeInput($b['author'] ?? '-') ?></td>
                        <td><?= formatDate($b['borrow_date']) ?></td>
                        <td><?= formatDate($b['due_date']) ?></td>
                        <td><?= strtotime($b['due_date']) < time() ? '<span class="badge bg-danger">Overdue</span>' : '<span class="badge bg-success">On Time</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Search by title, author, or ISBN..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): ?>
                    <tr>
                        <td><?= sanitizeInput($b['title']) ?></td>
                        <td><?= sanitizeInput($b['author'] ?? '-') ?></td>
                        <td><?= sanitizeInput($b['isbn'] ?? '-') ?></td>
                        <td><?= sanitizeInput($b['category'] ?? '-') ?></td>
                        <td>
                            <?php if ($b['available'] > 0): ?>
                            <span class="badge bg-success"><?= $b['available'] ?> / <?= $b['quantity'] ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($books)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3"><?= $search ? 'No books found.' : 'No books in the library yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
