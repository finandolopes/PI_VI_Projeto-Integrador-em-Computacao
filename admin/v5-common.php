<?php
session_start();require_once __DIR__.'/../php/sd_authorization.php';sdRequireRole('technician');
require_once __DIR__.'/../php/conexao.php';require_once __DIR__.'/../php/sd_service.php';
function v5e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function v5db():mysqli{global $conexao;if(!($conexao instanceof mysqli))throw new RuntimeException('Banco não configurado. Configure DB_NAME e DB_USER e desabilite o modo de demonstração para testar os dados.');sdSchema($conexao);return $conexao;}
function v5head(string $title):void{?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=v5e($title)?> — CONFINTER</title><link rel="stylesheet" href="assets/css/confinter-v5.css"><link rel="stylesheet" href="assets/css/admin-ui.css"></head><body><?php include __DIR__.'/navbar.php'; ?><div class="page-wrapper"><main id="main-content" class="v5"><?php }
function v5foot():void{?></main></div><?php include __DIR__.'/footer.php';?></body></html><?php }
