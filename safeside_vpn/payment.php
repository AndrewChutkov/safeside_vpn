<?php
require_once 'connect.php';
require_once 'buttons.php';
$paymentInformation = json_decode(file_get_contents('php://input'), true);
$status_payment = $paymentInformation['object']['status'];
$id_payment = $paymentInformation['object']['id'];
$description_payment = $paymentInformation['object']['description'];

file_put_contents('payment.txt', print_r($paymentInformation, true), FILE_APPEND);

/*Обработка платежа
 После того, как оплата пройдет, на урл это файла приходит уведомление в json'е.
 В бд, по id платежа, меняется статус, также берется id_user и username, чтобы именно тому пользователю,
 который оплатил пришел триггер, что оплата прошла. Формируется json, если в описании платежа 'Оплата подписки на 30 дней',
 то отправляем в кнопку 'оплата подписки', в противном случае — 'продление', и отправляется в bot.php
*/
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$sql = "UPDATE `payments` SET status_payment = '$status_payment'  WHERE id_payment = '$id_payment'";
$query = mysqli_query($conn, $sql);

$sql = "SELECT * FROM `payments` WHERE id_payment = '$id_payment'";
$query = mysqli_query($conn, $sql);

foreach ($query as $row_files) {
    $id_user = $row_files['id_user'];
    $username = $row_files['username'];
    if (!empty($id_user)) {
        break;
    }
}

mysqli_close($conn);
if ($description_payment == 'Оплата подписки на 30 дней') {
    $data = array
    (
        'callback_query' => array
        (

            'from' => array
            (
                'id' => $id_user,
                'username' => $username,
            ),

            'data' => $paid
        )
    );
} else {
    $data = array
    (
        'callback_query' => array
        (

            'from' => array
            (
                'id' => $id_user,
                'username' => $username,
            ),
            'message' => array
            (
                'text' => $description_payment,
            ),
            'data' => $prolongation,
        )
    );
}

$data_string = json_encode($data, JSON_UNESCAPED_UNICODE);
$curl = curl_init('https://example.com/bot.php');
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
// Принимаем в виде массива. (false - в виде объекта)
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
);
$result = curl_exec($curl);
curl_close($curl);

