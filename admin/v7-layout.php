<?php
require_once __DIR__.'/../php/v7_core.php';
function cfV7Base(): string {
    $dir=str_replace('\\','/',__DIR__);$root=rtrim(str_replace('\\','/',(string)($_SERVER['DOCUMENT_ROOT']??'')),'/');
    return $root!==''&&str_starts_with($dir,$root)?'/'.trim(substr($dir,strlen($root)),'/').'/':'/admin/';
}
function cfV7Head(string $title,string $subtitle=''): void {
    $base=cfV7Base();
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=cfEsc($title)?> | CONFINTER Admin</title><link rel="stylesheet" href="<?=cfEsc($base)?>assets/css/confinter-v7.css"></head><body class="<?=(!empty($_SESSION['cf_v7_density'])&&$_SESSION['cf_v7_density']==='compacto')?'cf-v7-compact':''?>"><?php include __DIR__.'/navbar.php';?><div class="page-wrapper"><main class="cf-v7" id="v7-main"><header class="cf-v7-header"><div><p class="cf-v7-overline">CONFINTER / GESTÃO INTEGRADA</p><h1><?=cfEsc($title)?></h1><?php if($subtitle!==''):?><p><?=cfEsc($subtitle)?></p><?php endif;?></div><nav class="cf-v7-head-links" aria-label="Acesso rápido"><a href="<?=cfEsc($base)?>meu-trabalho-v7.php">Meu trabalho</a><a href="<?=cfEsc($base)?>central-operacoes.php">Central</a></nav></header><?php
}
function cfV7Foot(): void {?></main></div><?php include __DIR__.'/footer.php';?></body></html><?php }
function cfV7Alert(string $message,string $type='info'): void { ?><div role="alert" class="cf-v7-alert cf-v7-<?=cfEsc($type)?>"><?=cfEsc($message)?></div><?php }
function cfV7Stat(string $label,string $value,string $link=''):void { ?><article class="cf-v7-stat"><span><?=cfEsc($label)?></span><strong><?=cfEsc($value)?></strong><?php if($link!==''):?><a href="<?=cfEsc($link)?>">Ver registros <span aria-hidden="true">→</span></a><?php endif;?></article><?php }
