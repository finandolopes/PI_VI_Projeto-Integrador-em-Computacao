<?php
header("Content-Type: application/json");
include_once "conexao.php";
$token = preg_replace("/[^A-Z0-9]/", "", strtoupper($_GET["token"] ?? ""));
if (!$token) { echo json_encode(["ok"=>false,"confirmado"=>false]); exit(); }
$stmt = $conexao->prepare("SELECT confirmado FROM iot_checkins WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
echo json_encode(["ok"=>$row!==null,"confirmado"=>(bool)($row["confirmado"]??false),"ts"=>time()]);
