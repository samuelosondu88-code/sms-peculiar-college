<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Library Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_book'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $author = sanitizeInput($_POST['author'] ?? '');
    $isbn = sanitizeInput($_POST['isbn'] ?? '');
    $publisher = sanitizeInput($_POST['publisher'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);

    if ($title) {
        if (isset($_POST['book_id']) && $_POST['book_id']) {
            $stmt = $db->prepare("UPDATE books SET title=?, author=?, isbn=?, publisher=?, category=?, quantity=?, available=? WHERE id=?");
            $stmt->execute([$title, $author, $isbn, $publisher, $category, $quantity, $quantity, $_POST['book_id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO books (title, author, isbn, publisher, category, quantity, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $author, $isbn, $publisher, $category, $quantity, $quantity]);
        }
        $msg = 'Book saved.';
    }
}

$books = $db->query("SELECT * FROM books ORDER BY title")->fetchAll();
$borrowings = $db->query("SELECT br.*, b.title as book_title, u.first_name, u.last_name FROM borrowings br JOIN books b ON br.book_id = b.id JOIN users u ON br.user_id = u.id WHERE br.status = 'borrowed' ORDER BY br.borrow_date DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-book-open me-2"></i>Library</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal"><i class="fas fa-plus me-1"></i>Add Book</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#books" data-bs-toggle="tab">Books (<?= count($books) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" href="#borrowed" data-bs-toggle="tab">Borrowed (<?= count($borrowings) ?>)</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="books">
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 datatable">
                    <thead><tr><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Qty</th><th>Available</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($books as $b): ?>
                        <tr>
                            <td><?= sanitizeInput($b['title']) ?></td>
                            <td><?= sanitizeInput($b['author'] ?? '-') ?></td>
                            <td><small><?= sanitizeInput($b['isbn'] ?? '-') ?></small></td>
                            <td><span class="badge bg-secondary"><?= sanitizeInput($b['category'] ?? '-') ?></span></td>
                            <td><?= $b['quantity'] ?></td>
                            <td><?= $b['available'] > 0 ? "<span class='badge bg-success'>{$b['available']}</span>" : "<span class='badge bg-danger'>0</span>" ?></td>
                            <td><button class="btn btn-sm btn-outline-primary">Edit</button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($books)): ?><tr><td colspan="7" class="text-center text-muted py-3">No books.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
    <div class="tab-pane fade" id="borrowed">
        <div class="card"><div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Book</th><th>Borrower</th><th>Borrowed</th><th>Due</th><th>Days Left</th></tr></thead>
                <tbody>
                    <?php foreach ($borrowings as $b):
                        $daysLeft = floor((strtotime($b['due_date']) - time()) / 86400);
                    ?>
                    <tr>
                        <td><?= sanitizeInput($b['book_title']) ?></td>
                        <td><?= sanitizeInput($b['first_name'] . ' ' . $b['last_name']) ?></td>
                        <td><?= formatDate($b['borrow_date']) ?></td>
                        <td><?= formatDate($b['due_date']) ?></td>
                        <td><span class="badge bg-<?= $daysLeft < 0 ? 'danger' : ($daysLeft < 3 ? 'warning' : 'success') ?>"><?= $daysLeft < 0 ? 'Overdue by ' . abs($daysLeft) . ' days' : $daysLeft . ' days' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($borrowings)): ?><tr><td colspan="5" class="text-center text-muted py-3">No books borrowed.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Add Book</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Author</label><input type="text" name="author" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Publisher</label><input type="text" name="publisher" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">Select</option><option>Textbook</option><option>Fiction</option><option>Reference</option><option>Science</option><option>Mathematics</option><option>English</option><option>History</option><option>Other</option></select></div>
                        <div class="col-md-6"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" value="1" min="1"></div>
                    </div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_book" value="1"><button type="submit" class="btn btn-primary">Add Book</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
