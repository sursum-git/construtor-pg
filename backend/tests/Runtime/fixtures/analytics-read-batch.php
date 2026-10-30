<?php
header('Content-Type: application/json');
echo json_encode(['nonce'=>bin2hex(random_bytes(16)), 'user'=>$_SERVER['HTTP_X_SURSUM_USER']??null]);
