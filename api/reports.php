<?php
require 'db.php';
$user_id = require_login();

$action = $_GET['action'] ?? '';

// ─── GET ALL REPORTS ────────────────────────────────────────────
if ($action === 'get_all') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM medical_reports WHERE user_id = ? ORDER BY report_date DESC");
    $stmt->execute(array($user_id));
    $reports = $stmt->fetchAll();
    echo json_encode(array("status" => "success", "data" => $reports));
}
// ─── ADD NEW REPORT (with auto PDF parsing) ─────────────────────
elseif ($action === 'add') {
    header('Content-Type: application/json');

    $report_date = isset($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d');
    $file_path = null;
    $parsed_values = array();

    // Handle PDF upload
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            echo json_encode(array("status" => "error", "message" => "Sadece PDF dosyası yükleyebilirsiniz."));
            exit;
        }

        $safeName = $user_id . '_' . $report_date . '_' . time() . '.pdf';
        $destPath = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destPath)) {
            $file_path = 'uploads/reports/' . $safeName;

            // Auto-parse PDF with Python script
            $pythonScript = __DIR__ . '/parse_pdf.py';
            $pdfFullPath = realpath($destPath);
            if ($pdfFullPath && file_exists($pythonScript)) {
                $cmd = escapeshellcmd("python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($pdfFullPath));
                $output = shell_exec($cmd . " 2>&1");
                if ($output) {
                    $json = json_decode($output, true);
                    if ($json && !isset($json['error'])) {
                        $parsed_values = $json;
                        // Auto-fill report_date from PDF if available
                        if (isset($parsed_values['report_date'])) {
                            $report_date = $parsed_values['report_date'];
                        }
                    }
                }
            }
        }
    }

    // Lab values: Use parsed values as base, override with manual POST values
    $fields = array(
        'glucose', 'cholesterol_total', 'ldl', 'hdl', 'triglyceride',
        'hba1c', 'iron', 'ferritin', 'b12', 'vitamin_d', 'tsh',
        'hemoglobin', 'creatinine', 'alt_sgpt', 'ast_sgot'
    );

    $columns = array('user_id', 'report_date', 'file_path');
    $placeholders = array('?', '?', '?');
    $values = array($user_id, $report_date, $file_path);

    foreach ($fields as $f) {
        // Manual POST value overrides parsed value
        if (isset($_POST[$f]) && $_POST[$f] !== '') {
            $val = $_POST[$f];
        } elseif (isset($parsed_values[$f])) {
            $val = $parsed_values[$f];
        } else {
            $val = null;
        }
        $columns[] = $f;
        $placeholders[] = '?';
        $values[] = $val;
    }

    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    $columns[] = 'notes';
    $placeholders[] = '?';
    $values[] = $notes;

    $sql = "INSERT INTO medical_reports (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode(array(
        "status" => "success",
        "message" => "Tahlil kaydedildi!",
        "id" => $pdo->lastInsertId(),
        "parsed" => $parsed_values
    ));
}
// ─── DELETE REPORT ──────────────────────────────────────────────
elseif ($action === 'delete') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    if ($id <= 0) {
        echo json_encode(array("status" => "error", "message" => "Geçersiz ID."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT file_path FROM medical_reports WHERE id = ? AND user_id = ?");
    $stmt->execute(array($id, $user_id));
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(array("status" => "error", "message" => "Kayıt bulunamadı."));
        exit;
    }

    if ($row['file_path'] && file_exists('../' . $row['file_path'])) {
        unlink('../' . $row['file_path']);
    }

    $pdo->prepare("DELETE FROM medical_reports WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
    echo json_encode(array("status" => "success", "message" => "Tahlil silindi."));
}
else {
    header('Content-Type: application/json');
    echo json_encode(array("status" => "error", "message" => "Bilinmeyen işlem."));
}
?>
