<?php


header('Content-Type: application/json');

try {

    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=ohms_velzon_db;charset=utf8mb4",
        "root",
        "ujan1999"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT
            id,
            customer_name,
            email,
            date,
            phone,
            status
        FROM customer
        ORDER BY id DESC
    ";

    $stmt = $pdo->query($sql);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($customers as &$row) {

        $row['date'] = date(
            'd M, Y',
            strtotime($row['date'])
        );
    }

    echo json_encode([
        'status' => true,
        'data' => $customers
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}

