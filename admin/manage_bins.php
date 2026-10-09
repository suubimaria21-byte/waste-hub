<?php
require_once '../includes/auth.php';
requireRole('admin');
header('Location: dashboard.php');
exit;
?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetBinForm() {
 document.getElementById('binModalTitle').innerText = 'Add New Bin';
 document.getElementById('binId').value = '';
 document.getElementById('location').value = '';
 document.getElementById('capacity').value = 100;
 document.getElementById('current_level').value = 0;
 document.getElementById('status').value = 'empty';
}

function editBin(b) {
 document.getElementById('binModalTitle').innerText = 'Edit Bin';
 document.getElementById('binId').value = b.id;
 document.getElementById('location').value = b.location;
 document.getElementById('capacity').value = b.capacity;
 document.getElementById('current_level').value = b.current_level;
 document.getElementById('status').value = b.status;
 new bootstrap.Modal(document.getElementById('binModal')).show();
}
</script>
</body>
</html>