<?php

require_once 'connect.php';

$date = date('Y-m-d H:i:s');
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$sql = "SELECT * FROM `files`";
$query = mysqli_query($conn, $sql);

foreach ($query as $row_files) {
    $id_user = $row_files['id_user'];
    $date_end = $row_files['date_end'];
    $date_before_one_day_end = date('Y-m-d H:i:s', strtotime($date_end . '- 1 days'));
    $date = date('Y-m-d H:i:s',strtotime('now'));

    if (!empty($id_user) and $date > $date_before_one_day_end) {
        $data = array
        (
            'callback_query' => array
            (

                'from' => array
                (
                    'id' => $id_user,
                ),

                'data' => 'reminder',
            )
        );

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

    }

}

mysqli_close($conn);

