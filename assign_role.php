<?php
$u = App\Models\User::find(1);
if ($u) {
    $u->syncRoles(['admin', 'owner', 'housekeeper']);
    echo "Assigned roles to user: " . $u->name . "\n";
} else {
    echo "User found not found.\n";
}
exit();
