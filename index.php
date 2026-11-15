<?php
session_start();
include_once('php/conexao.php');
include_once('php/metrics.php');

// Rastreia visitas para o painel administrativo quando o banco estiver disponível.
if (defined('CONFINTER_DB_READY') && CONFINTER_DB_READY && !isset($_SESSION['visita_registrada'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
    $tempo_visita = date('H:i:s');
    $data_visita = date('Y-m-d');

    $sql_visita = "INSERT INTO contador_visitas (data_visita, ip_address, user_agent, tempo)
                   VALUES ('$data_visita', '" . mysqli_real_escape_string($conexao, $ip) . "',
                           '" . mysqli_real_escape_string($conexao, $user_agent) . "',
                           '$tempo_visita')";

    if (mysqli_query($conexao, $sql_visita)) {
        $_SESSION['visita_registrada'] = true;
    }
}
$confinter_demo_mode = defined('CONFINTER_DEMO_MODE') && CONFINTER_DEMO_MODE;

// Gera token CSRF para login
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verificar se o formulário de login foi submetido
if (($_SERVER["REQUEST_METHOD"] ?? "GET") == "POST") {
    include_once('php/processa_login.php');
}
// Consulta ao banco de dados para recuperar as imagens do carrossel (opcional nesta landing).
$result = null;
if (defined('CONFINTER_DB_READY') && CONFINTER_DB_READY) {
    $sql = "SELECT * FROM imagens_carrossel";
    $result = mysqli_query($conexao, $sql);
}


// Verificar se a variável de sessão sucesso_depoimento está definida
if (isset($_SESSION['sucesso_depoimento']) && $_SESSION['sucesso_depoimento']) {
    // Exibir a mensagem de sucesso
    echo "<p class='text-success'>Depoimento enviado com sucesso!</p>";
    // Remover a variável de sessão para que a mensagem não seja exibida novamente após o próximo carregamento da página
    unset($_SESSION['sucesso_depoimento']);
}

?>



<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"/><meta content="width=device-width,initial-scale=1" name="viewport"/><meta content="#084d91" name="theme-color"/><title>CONFINTER | Experiência imersiva 3D — prévia</title><style>
:root{--blue:#084e97;--deep:#062f64;--cyan:#18a5ce;--gold:#f1b83c;--ink:#153250;--muted:#5c7187;--paper:#f6faff;--ease:cubic-bezier(.19,1,.22,1)}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font:16px/1.6 system-ui,-apple-system,'Segoe UI',sans-serif;color:var(--ink);background:#f4f9fe;overflow-x:hidden}button,input,textarea{font:inherit}a{color:inherit;text-decoration:none}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid #f5b631;outline-offset:3px}#progress{position:fixed;z-index:200;top:0;left:0;width:0;height:4px;background:linear-gradient(90deg,#f2b831,#3bccff);box-shadow:0 0 14px #2fd3ff;transition:width .1s}
header{position:fixed;z-index:100;top:4px;width:100%;background:rgba(255,255,255,.91);backdrop-filter:blur(20px);border-bottom:1px solid #d9e8f5;box-shadow:0 8px 30px #0a31571b}header .bar{max-width:1370px;margin:auto;min-height:77px;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}.logo{width:182px;max-height:55px;object-fit:contain}.nav{display:flex;align-items:center;gap:4px;flex-wrap:wrap;justify-content:flex-end}.nav a{border-radius:12px;font-weight:700;font-size:12px;padding:10px 9px;color:#123d6b;transition:transform .3s,background .3s,box-shadow .3s;transform-style:preserve-3d}.nav a:hover,.nav a.active{background:#e5f4ff;color:var(--blue);transform:translateZ(12px) translateY(-2px);box-shadow:0 6px 13px #0085df1d}.hamb{display:none;border:0;background:#e4f2fc;padding:8px 14px;border-radius:10px;color:#074a87;font-weight:700}.social{position:fixed;right:13px;top:39%;z-index:101;display:grid;gap:9px}.social a{height:45px;width:45px;display:grid;place-items:center;color:white;font-size:19px;border-radius:13px 3px 3px 13px;box-shadow:0 6px 18px #12385833;transform-style:preserve-3d;transition:transform .35s}.social a:hover{transform:translateX(-7px) rotateY(-19deg) scale(1.08)}.social .ig{background:linear-gradient(135deg,#ae3996,#f36f5c)}.social .wa{background:#19ad69}.social .em{background:#08579d}.social span{font-size:10px;font-weight:900}.container{width:min(1180px,calc(100% - 48px));margin:auto}.eyebrow{text-transform:uppercase;letter-spacing:.20em;font-size:12px;font-weight:900;color:#1886bf}.section{padding:110px 0;position:relative;isolation:isolate}.section h2{font-size:clamp(30px,4vw,51px);line-height:1.12;margin:12px 0 20px;color:#083e78}.section p.lead{max-width:760px;font-size:18px;color:#5a7187}.section-intro{text-align:center;max-width:850px;margin:0 auto 44px}.section-intro .lead{margin:auto}.badge{display:inline-flex;align-items:center;gap:8px;padding:7px 15px;border:1px solid #9fd6fb6b;background:#effaff12;border-radius:99px;font-size:11px;font-weight:900;letter-spacing:.1em}.pill{border:0;border-radius:12px;background:linear-gradient(110deg,#f6ca50,#edaa1d);color:#243241;box-shadow:0 10px 30px #d89f3850;padding:14px 22px;font-weight:900;transition:transform .3s,box-shadow .3s}.pill:hover{transform:translateY(-5px) translateZ(10px);box-shadow:0 18px 35px #d89f3870}.pill.secondary{background:#fff;color:#09528d;box-shadow:0 9px 23px #02214636}.hero{min-height:740px;height:min(860px,100svh);margin-top:77px;position:relative;overflow:hidden;background:#032954;color:white;perspective:1100px}.hero-bg{position:absolute;inset:-6%;background-size:cover;background-position:center;filter:brightness(.50) saturate(.95);transform:translate3d(var(--bgx,0px),var(--bgy,0px),0) scale(1.13);transition:background-image .35s,transform 1s var(--ease)}.hero:before{content:'';position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,#052a59f5 1%,#063663bb 48%,#043e6d35 100%)}.hero .layer-grid{position:absolute;inset:0;z-index:2;opacity:.17;background-image:linear-gradient(#8fe6ff 1px,transparent 1px),linear-gradient(90deg,#8fe6ff 1px,transparent 1px);background-size:75px 75px;transform:perspective(480px) rotateX(54deg) scale(2);transform-origin:50% 86%;mask-image:linear-gradient(transparent,#000)}.hero .inner{max-width:1300px;position:relative;z-index:5;display:grid;align-items:center;grid-template-columns:1.05fr .95fr;height:100%;margin:auto;padding:45px 45px;gap:25px}.hero-copy{transform-style:preserve-3d}.hero-copy h1{font-size:clamp(37px,5vw,70px);line-height:1.05;letter-spacing:-.045em;max-width:680px;margin:19px 0}.hero-copy h1 em{font-style:normal;color:#f4bd41}.hero-copy p{max-width:610px;color:#deedfd;font-size:18px}.hero-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:27px}.hero-copy>*{animation:rise .85s both}.hero-copy h1{animation-delay:.1s}.hero-copy p{animation-delay:.2s}.hero-actions{animation-delay:.3s}@keyframes rise{from{opacity:0;transform:translate3d(0,50px,-90px) rotateX(-15deg)}to{opacity:1;transform:translate3d(0,0,0)}}.scene{height:540px;position:relative;transform-style:preserve-3d;transform:perspective(950px) rotateY(var(--ry,-12deg)) rotateX(var(--rx,6deg));transition:transform .16s ease-out}.orbit{position:absolute;inset:15% 11%;border:2px solid #76d7ff69;border-radius:50%;transform-style:preserve-3d;transform:rotateY(69deg) rotateX(20deg);box-shadow:0 0 25px #67cfff35,inset 0 0 30px #75cfff1c;animation:spin 15s linear infinite}.orbit.two{inset:7% 20%;transform:rotateX(68deg) rotateZ(-35deg);animation-duration:21s;border-color:#efbc6688}.orbit.three{inset:21% 8%;transform:rotateY(-40deg) rotateX(35deg);animation-direction:reverse;animation-duration:18s}@keyframes spin{50%{transform:rotateY(180deg) rotateX(50deg)}100%{transform:rotateY(360deg) rotateX(20deg)}}.credit-card{position:absolute;left:16%;top:25%;width:69%;height:48%;border-radius:24px;padding:27px;background:linear-gradient(130deg,#0e74b5,#054174 58%,#032c5c);border:1px solid #79d8faab;box-shadow:25px 35px 60px #00162c99,inset 0 1px 2px #dbfbff69;transform:translateZ(100px) rotateY(-14deg) rotateX(7deg);transform-style:preserve-3d;animation:float 5s ease-in-out infinite}.credit-card:before{content:'';position:absolute;inset:11px;border:1px solid #d6f5ff32;border-radius:19px}.credit-card:after{content:'';position:absolute;left:7px;right:7px;top:96%;height:16px;border-radius:0 0 16px 16px;background:#03284f;transform:rotateX(74deg);transform-origin:top}.credit-top{font-size:20px;font-weight:950;letter-spacing:.06em}.chip{width:52px;height:37px;border:1px solid #ffeab8;border-radius:8px;background:linear-gradient(145deg,#ffe5a8,#b58b3f);margin-top:45px;box-shadow:0 3px 14px #0002}.credit-bottom{display:flex;justify-content:space-between;align-items:end;margin-top:25px}.credit-bottom small{letter-spacing:.18em;color:#d2eefe}.coin{position:absolute;right:4%;top:8%;width:130px;height:130px;border-radius:50%;display:grid;place-items:center;font-size:60px;font-weight:900;background:linear-gradient(145deg,#ffe3a1,#e5a729);border:9px ridge #fee9a6;box-shadow:15px 23px 25px #02284f99;transform:translateZ(190px) rotateY(22deg);animation:float 4.2s ease-in-out infinite reverse}.float-tag{position:absolute;background:#e9f8ffef;color:#064875;padding:9px 15px;border-radius:14px;font-size:12px;font-weight:900;box-shadow:0 10px 28px #01264a88;transform:translateZ(175px);animation:float 3.8s ease-in-out infinite}.float-tag.t1{left:2%;top:22%}.float-tag.t2{right:0;bottom:15%;animation-delay:1s}.float-tag.t3{left:8%;bottom:10%;animation-delay:.5s}@keyframes float{50%{translate:0 -17px}}.hero-controls{position:absolute;z-index:9;bottom:22px;left:50%;transform:translateX(-50%);display:flex;gap:9px;align-items:center;background:#0627449e;padding:10px 16px;border-radius:99px;backdrop-filter:blur(12px)}.hero-controls button{border:1px solid #92c7e966;background:#fff2;color:white;width:33px;height:33px;border-radius:50%;font-weight:900}.dot{width:33px!important;height:6px!important;border-radius:99px!important;background:#ffffff6b!important;border:0!important;transition:background .25s}.dot.on{background:#f4be3e!important}.mini-scroll{position:absolute;bottom:28px;right:35px;z-index:8;letter-spacing:.16em;font-size:10px;font-weight:900;color:#d8efff}.tilt{position:relative;transform-style:preserve-3d;transform:perspective(850px) rotateX(var(--tx,0deg)) rotateY(var(--ty,0deg));transition:transform .18s ease-out,box-shadow .25s}.tilt>*{transform:translateZ(20px)}.tilt h3{transform:translateZ(40px)}.section.s-about{background:linear-gradient(#fff,#f3faff)}.two{display:grid;grid-template-columns:1fr 1fr;gap:70px;align-items:center}.depth-stack{position:relative;height:380px;perspective:900px}.depth-stack .panel{position:absolute;width:70%;height:240px;left:12%;top:60px;border-radius:22px;box-shadow:12px 25px 45px #06457432;display:grid;place-items:center;text-align:center;padding:25px;font-weight:900;backface-visibility:hidden}.depth-stack .panel.back{transform:translate3d(65px,-65px,-70px) rotateY(-17deg);background:#cdeafd}.depth-stack .panel.mid{transform:translate3d(-38px,20px,10px) rotateY(14deg);background:#d6eafd}.depth-stack .panel.front{transform:translateZ(75px) rotateY(-7deg);background:linear-gradient(130deg,#0f76b5,#054174);color:white;font-size:23px;animation:float 5s infinite}.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}.tile{padding:30px 25px;border-radius:22px;background:white;border:1px solid #d9e9f6;box-shadow:0 14px 37px #07518b17;min-height:240px}.tile .symbol{display:grid;place-items:center;width:62px;height:62px;border-radius:17px;background:linear-gradient(135deg,#def4ff,#a8d9f4);color:#075a96;font-size:27px;margin-bottom:20px;box-shadow:6px 9px 0 #c9e7f9;transform:translateZ(50px)}.tile h3{margin:0 0 10px;color:#073f7a;font-size:21px}.tile p{margin:0;color:#61768b;font-size:14px}.values{background:linear-gradient(135deg,#e8f6ff,#f8fcff)}.services{background:linear-gradient(125deg,#073866,#075994);color:white}.services h2,.services .eyebrow{color:white}.services .tile{background:linear-gradient(145deg,#ffffff,#dceeff);color:#123f68}.services .tile p{color:#536f85}.services .tile{min-height:310px}.services .tile a{display:inline-block;color:#0074a9;font-size:13px;font-weight:900;margin-top:18px}.stage{background:#052e5f;color:white;overflow:hidden}.stage h2{color:white}.stage .lead{color:#c6e8fc}.stage-flow{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;perspective:1200px;margin:60px auto 28px}.stage-flow button{border:1px solid #8ad5ff77;background:linear-gradient(150deg,#0a568f,#083963);min-height:190px;padding:20px 12px;color:white;border-radius:20px;box-shadow:0 12px 25px #001c38;transform:rotateY(-8deg) translateZ(0);transition:transform .4s,background .3s;position:relative}.stage-flow button:hover,.stage-flow button.active{background:linear-gradient(150deg,#137cb2,#0b4c89);transform:rotateY(0) translateZ(65px) translateY(-20px);box-shadow:0 22px 36px #001932}.stage-flow button:not(:last-child):after{content:'→';position:absolute;right:-19px;top:44%;z-index:4;color:#f7c456;font-size:20px}.stage-flow .num{font-size:13px;color:#aee7ff}.stage-flow .emoji{display:block;font-size:45px;margin:8px auto}.stage-flow strong{display:block;font-size:14px}.stage-detail{max-width:740px;margin:auto;min-height:85px;text-align:center;color:#d6f0ff}.stage-actions{text-align:center}.stage-actions button{margin:5px}.form-panel{border-radius:27px;padding:30px;background:white;box-shadow:15px 25px 50px #16446a25;border:1px solid #d7edfb}.fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}.fields label{display:flex;flex-direction:column;font-size:13px;font-weight:800;color:#346078;gap:6px}.fields input,.fields textarea{width:100%;padding:12px 13px;border-radius:10px;border:1px solid #c8deeb;background:#f9fcff}.fields label.wide{grid-column:1/-1}.form-note{font-size:13px;color:#5f788a}.faq-item{padding:20px 23px;margin-bottom:14px;border-radius:15px;background:#fff;border:1px solid #d5e8f5;box-shadow:0 8px 25px #003f7910;transform-style:preserve-3d;transition:transform .3s}.faq-item:hover{transform:perspective(800px) rotateX(2deg) translateZ(14px)}.faq-item summary{font-weight:900;cursor:pointer;color:#0b4c84}.faq-item p{margin-bottom:0;color:#58728b}.testimonials{background:#ebf5fd}.quote{padding:33px;border-radius:22px;background:white;box-shadow:0 15px 38px #054e8630;font-size:18px;font-weight:600;color:#17547e}.contact{background:#fff}.map-card{min-height:280px;border-radius:25px;position:relative;overflow:hidden;background:radial-gradient(circle at 55% 42%,#c8eafa,#8dbadd);display:grid;place-items:center;box-shadow:0 17px 38px #06518a36}.map-card:before{content:'';position:absolute;inset:-20%;background:repeating-linear-gradient(35deg,transparent 0 33px,#fff8 34px 37px,transparent 38px 70px),repeating-linear-gradient(120deg,transparent 0 50px,#fff8 51px 53px,transparent 54px 85px);transform:perspective(600px) rotateX(45deg) scale(1.45)}.map-card .pin{z-index:2;font-size:55px;filter:drop-shadow(8px 15px 8px #13507a77);animation:float 3s infinite}.map-card strong{z-index:2;background:#fff;padding:7px 15px;border-radius:13px;color:#104a83}footer{padding:35px 20px;background:#032d59;color:#bfd8ed;text-align:center;font-size:13px}.reveal{opacity:1;transform:none}.has-observer .reveal{opacity:0;transform:perspective(950px) translateY(75px) rotateX(12deg) scale(.95);transition:transform .9s var(--ease),opacity .9s}.has-observer .reveal.visible{opacity:1;transform:perspective(950px) translateY(0) rotateX(0) scale(1)}.notice{background:#fff9df;border:1px solid #f0d37a;border-radius:13px;padding:12px 16px;color:#70551d;font-size:13px;margin-top:20px}.spacer{height:15px}
@media(max-width:1100px){.nav{gap:0}.nav a{font-size:11px;padding:8px 6px}.hero .inner{padding:35px 35px}.scene{height:460px}.grid-4{grid-template-columns:repeat(2,1fr)}}@media(max-width:780px){header .bar{min-height:65px}.logo{width:146px}.hamb{display:block}.nav{display:none;position:absolute;left:0;right:0;top:100%;padding:15px;background:#fff;box-shadow:0 17px 20px #00294933}.nav.open{display:grid;grid-template-columns:1fr 1fr}.nav a{font-size:13px;padding:11px}.hero{height:auto;min-height:960px;margin-top:65px}.hero .inner{grid-template-columns:1fr;padding:58px 25px 110px;align-content:start}.scene{height:415px}.hero-copy h1{font-size:clamp(36px,9vw,58px)}.two{grid-template-columns:1fr;gap:20px}.grid-3{grid-template-columns:1fr}.grid-4{grid-template-columns:repeat(2,1fr)}.stage-flow{grid-template-columns:1fr 1fr}.stage-flow button:not(:last-child):after{display:none}.social{right:3px}.social a{width:35px;height:38px}.section{padding:80px 0}.depth-stack{height:290px}.depth-stack .panel{height:200px}}@media(max-width:480px){.grid-4,.stage-flow,.fields{grid-template-columns:1fr}.hero{min-height:930px}.hero-copy p{font-size:16px}.scene{height:355px}.credit-card{padding:17px}.chip{margin-top:25px}.credit-top{font-size:14px}.coin{width:85px;height:85px;font-size:41px}.hero-controls{bottom:16px}.float-tag{font-size:10px}.section h2{font-size:34px}}@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important;scroll-behavior:auto!important}.has-observer .reveal{opacity:1;transform:none}.scene{transform:none}.credit-card{animation:none}}
</style><style id="confinter-php-bridge">
.form-panel form {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;text-align:left}
.form-panel form .form-group {min-width:0} .form-panel form label{font-weight:650;color:#164576;display:block;margin:0 0 5px}
.form-panel form input:not([type=checkbox]):not([type=radio]),.form-panel form textarea,.form-panel form select{width:100%;max-width:100%;background:white;border:1px solid #b9d2e3;border-radius:12px;padding:12px;color:#163e60;box-sizing:border-box}
.form-panel form button,.form-panel form input[type=submit]{background:#0b73bd;color:white;border:0;border-radius:16px;padding:12px 20px;cursor:pointer}
.form-panel form .form-group:has(textarea), .form-panel form .form-group:has(button),.form-panel form .form-group:has([type=submit]){grid-column:1/-1}
.confinter-dynamic-reviews section{padding:0;background:transparent}.confinter-dynamic-reviews .swiper-wrapper{display:flex;flex-wrap:wrap;gap:16px}.confinter-dynamic-reviews .swiper-slide{flex:1 1 220px;max-width:350px;background:white;padding:20px;border-radius:18px;box-shadow:0 12px 30px #092b521a}
@media(max-width:650px){.form-panel form{grid-template-columns:1fr}} </style><link rel="stylesheet" href="assets/css/confinter-accessibility.css">
<script defer src="assets/js/confinter-accessibility.js"></script>
<?php if (!empty($confinter_demo_mode)): ?>
<style>.demo-mode-banner{position:fixed;left:50%;transform:translateX(-50%);top:84px;z-index:1200;background:linear-gradient(135deg,#ffe8a8,#ffd777);color:#17365d;padding:10px 18px;border-radius:999px;box-shadow:0 12px 30px rgba(0,0,0,.15);font-size:12px;font-weight:800}.demo-mode-banner strong{font-weight:900}</style>
<?php endif; ?>
<link rel="stylesheet" href="assets/css/confinter-scribble-parallax.css">
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/examples/js/loaders/GLTFLoader.js"></script>
<script defer src="assets/js/confinter-service-scenes.js"></script>
<link rel="stylesheet" href="assets/css/confinter-design-system-2026.css"><link rel="stylesheet" href="assets/css/confinter-editorial-2026.css"><script defer src="assets/js/confinter-design-system-2026.js"></script><link rel="stylesheet" href="assets/css/confinter-v7-editorial.css">
</head><body><?php if (!empty($confinter_demo_mode)): ?><div class="demo-mode-banner">Modo de demonstração ativo · <strong>sem MySQL</strong> · ideal para navegação e ilustrações do projeto.</div><?php endif; ?><div id="progress"></div><header><div class="bar"><a aria-label="Início CONFINTER" href="#inicio"><img alt="CONFINTER" class="logo" src="assets/media/v7/institucional_01.webp"/></a><button aria-controls="nav" aria-expanded="false" class="hamb" id="hamb">☰ Menu</button><nav aria-label="Menu principal" class="nav" id="nav"><a href="#sobre">Sobre</a><a href="#valores">Nossos Valores</a><a href="#servicos">Serviços</a><a href="#requi">Requisições</a><a href="#faq">Dúvidas</a><a href="#depoimentos">Depoimentos</a><a href="#chegar">Como Chegar</a><a href="#login-demo">Login</a></nav></div></header><aside aria-label="Redes sociais" class="social"><a aria-label="Instagram" class="ig" href="https://www.instagram.com/confintersp?igsh=a3NuaGJrem5pYzZu" rel="noopener" target="_blank">◎</a><a aria-label="WhatsApp" class="wa" href="https://api.whatsapp.com/send?phone=11948016298" rel="noopener" target="_blank">✆</a><a aria-label="E-mail" class="em" href="mailto:contato@confinter.com.br">✉</a></aside>
<main><section aria-label="Carrossel 3D" class="hero" id="inicio"><div class="hero-bg" id="hero-bg"></div><div class="layer-grid"></div><div class="inner"><div class="hero-copy"><span class="badge">✦ CONFINTER · CONSOLIDANDO SONHOS</span><h1 id="hero-title">Seu futuro merece <em>novas perspectivas.</em></h1><p id="hero-sub">Soluções financeiras e atendimento humano, agora apresentados em uma experiência digital tridimensional e interativa.</p><div class="hero-actions"><a class="pill" href="#servicos">Conhecer serviços ↗</a><a class="pill secondary" href="#requi">Fazer uma requisição</a><a class="pill secondary" href="service-desk.php">Abrir Service Desk</a></div><div class="notice" style="background:#eaf8ff15;color:#dcf5ff;border-color:#8edaff55;max-width:480px">Explore três objetos 3D próprios para crédito, proteção financeira e atendimento. Eles acompanham as transições do carrossel.</div></div><div aria-label="Representação interativa tridimensional de um cartão CONFINTER" class="scene" id="scene"><div class="hero-webgl" id="hero-webgl" aria-hidden="true"></div><div class="scribble-three" id="scribble-three" aria-hidden="true"></div><div class="orbit"></div><div class="orbit two"></div><div class="orbit three"></div><div class="coin">✦</div><div class="credit-card"><div class="credit-top">CONFINTER</div><div class="chip"></div><div class="credit-bottom"><div><small>CONSOLIDANDO</small><br/><strong>SONHOS</strong></div><strong>✦</strong></div></div><span class="float-tag t1">◈ Atendimento humano</span><span class="float-tag t2">✓ Soluções financeiras</span><span class="float-tag t3">↗ Novas possibilidades</span></div></div><button type="button" id="wireframe-toggle" class="wireframe-toggle" aria-pressed="true">✦ Objetos 3D: ligados</button><div class="hero-controls"><button aria-label="Slide anterior" id="prev">‹</button><button aria-label="Slide 1" class="dot on" data-slide="0"></button><button aria-label="Slide 2" class="dot" data-slide="1"></button><button aria-label="Slide 3" class="dot" data-slide="2"></button><button aria-label="Próximo slide" id="next">›</button></div><span class="mini-scroll">ROLE PARA EXPLORAR ↓</span></section>
<section class="section s-about" id="sobre"><div class="container two"><div class="reveal"><span class="eyebrow">Quem somos</span><h2>A CONFINTER com a identidade que você conhece.</h2><p class="lead">Nossa missão é conectar pessoas às soluções financeiras adequadas, com orientação próxima e atendimento de qualidade. A experiência digital evolui, sem apagar nossa história.</p><p>As camadas à direita respondem à rolagem e à perspectiva. Cada parte do site ganha profundidade sem alterar a estrutura dos serviços originais.</p><a class="pill" href="#valores">Conheça nossos valores →</a></div><div aria-label="Cartões institucionais em perspectiva" class="depth-stack reveal"><div class="panel back">TRADIÇÃO</div><div class="panel mid">CONFIANÇA</div><div class="panel front">CONFINTER<br/><small>Consolidando sonhos</small></div></div></div></section>
<section class="section values" id="valores"><div class="container"><div class="section-intro reveal"><span class="eyebrow">Nossa essência</span><h2>Missão, Visão e Valores</h2><p class="lead">Cartões com relevo, sombras dinâmicas e perspectiva que acompanha o movimento — não apenas efeitos soltos.</p></div><div class="grid-3"><article class="tile tilt reveal"><span class="symbol">◎</span><h3>Missão</h3><p>Atender com responsabilidade, respeito e foco nas necessidades de cada pessoa.</p></article><article class="tile tilt reveal"><span class="symbol">◈</span><h3>Visão</h3><p>Oferecer experiências financeiras cada vez mais acessíveis, modernas e confiáveis.</p></article><article class="tile tilt reveal"><span class="symbol">✧</span><h3>Valores</h3><p>Transparência, ética, comprometimento e valorização das relações humanas.</p></article></div></div></section>
<section class="section services" id="servicos"><div class="container"><div class="section-intro reveal"><span class="eyebrow">Nossos Serviços</span><h2>Soluções com uma experiência 3D em cada cartão.</h2><p class="lead" style="color:#c5e7fa">Passe o mouse sobre os serviços: a geometria inclina, os ícones avançam no espaço e a luz acompanha o ponteiro.</p></div><div class="grid-4"><article class="tile tilt reveal"><span class="symbol">▣</span><h3>Consultoria</h3><p>Orientação especializada para diferentes necessidades financeiras.</p><a href="#requi">Solicitar atendimento ↗</a></article><article class="tile tilt reveal"><span class="symbol">✦</span><h3>Crédito</h3><p>Atendimento personalizado para conhecer alternativas de crédito.</p><a href="#requi">Conhecer opções ↗</a></article><article class="tile tilt reveal"><span class="symbol">◈</span><h3>Seguros</h3><p>Conheça opções para proteger planos, patrimônio e conquistas.</p><a href="#requi">Falar com a equipe ↗</a></article><article class="tile tilt reveal"><span class="symbol">⌘</span><h3>Atendimento</h3><p>Experiência próxima e canais digitais de contato com a CONFINTER.</p><a href="#chegar">Ver contatos ↗</a></article></div></div></section>
<section class="section stage" id="tecnologia"><div class="container"><div class="section-intro reveal"><span class="eyebrow" style="color:#8adfff">Projeto Integrador VI</span><h2>Um sistema que ganha vida enquanto você navega.</h2><p class="lead">Toque em cada etapa para navegar por uma jornada tridimensional de um incidente — do sensor ao atendimento técnico.</p></div><div class="stage-flow reveal"><button class="active" data-stage="0"><span class="num">ETAPA 01</span><span class="emoji">▣</span><strong>Sensor ESP32</strong></button><button data-stage="1"><span class="num">ETAPA 02</span><span class="emoji">⇄</span><strong>API de telemetria</strong></button><button data-stage="2"><span class="num">ETAPA 03</span><span class="emoji">⚙</span><strong>Motor de regras</strong></button><button data-stage="3"><span class="num">ETAPA 04</span><span class="emoji">▤</span><strong>Chamado automático</strong></button><button data-stage="4"><span class="num">ETAPA 05</span><span class="emoji">✓</span><strong>Resolução técnica</strong></button></div><div aria-live="polite" class="stage-detail" id="stage-detail">O dispositivo capta uma métrica e prepara uma leitura identificada para envio.</div><div class="stage-actions"><button class="pill secondary" id="stage-back">← Anterior</button><button class="pill" id="stage-next">Próxima etapa →</button><button class="pill secondary" id="stage-play">▶ Reproduzir</button></div></div></section>
<section class="section" id="requi"><div class="container two"><div class="reveal"><span class="eyebrow">Requisições</span><h2>Seu crédito, uma jornada mais simples.</h2><p class="lead">A experiência visual foi modernizada, mas o formulário continua conectado ao processamento original da CONFINTER.</p><a href="institucional-original.php#requi" class="pill">Abrir formulário clássico</a></div><div class="form-panel tilt reveal"><h3>Análise de Crédito</h3><form action="php/process.php" id="form-requisicao" method="POST">
<div class="form-group">
<label for="nome">Nome completo:</label>
<input class="form-control" id="nome" name="nome" placeholder="Nome completo" required="" type="text"/>
</div>
<div class="form-row">
<div class="form-group col-md-4">
<label for="data_nascimento">Data de Nascimento:</label>
<input class="form-control" id="data_nascimento" name="data_nascimento" placeholder="Insira sua data de nascimento" required="" type="text"/>
</div>
<div class="form-group col-md-4">
<label for="telefone">Telefone:</label>
<input class="form-control" id="telefone" maxlength="15" name="telefone" placeholder="(00) 00000-0000" required="" type="tel"/>
</div>
</div>
<div class="form-row">
<div class="form-group col-md-4">
<label for="email">E-mail:</label>
<input class="form-control" id="email" name="email" placeholder="seu@email.com" required="" type="email"/>
</div>
<div class="form-group col-md-2">
<label for="horario_contato">Horário para Contato:</label>
<input class="form-control" id="horario_contato" name="horario_contato" required="" type="time"/>
</div>
</div>
<div class="form-group">
<label for="tipo">Tipo:</label>
<textarea class="form-control" id="tipo" maxlength="250" name="tipo" rows="3"></textarea>
</div>
<div class="form-group">
<label>Categoria:</label>
<div class="form-row">
<div class="col-md-3">
<div class="form-check">
<input class="form-check-input" id="aposentado" name="categoria[]" type="checkbox" value="Aposentado"/>
<label class="form-check-label" for="aposentado">Aposentado</label>
</div>
</div>
<div class="col-md-3">
<div class="form-check">
<input class="form-check-input" id="pensionista" name="categoria[]" type="checkbox" value="Pensionista"/>
<label class="form-check-label" for="pensionista">Pensionista</label>
</div>
</div>
<div class="col-md-3">
<div class="form-check">
<input class="form-check-input" id="servidor_publico" name="categoria[]" type="checkbox" value="Servidor Público"/>
<label class="form-check-label" for="servidor_publico">Servidor Público</label>
</div>
</div>
<div class="col-md-3">
<div class="form-check">
<input class="form-check-input" id="outros_check" name="categoria[]" type="checkbox" value="Outros"/>
<label class="form-check-label" for="outros_check">Outros</label>
</div>
</div>
</div>
</div>
<div class="form-group" id="outros_info_div" style="display: none;">
<label for="outros_info">Insira outras informações se necessário:</label>
<input class="form-control" id="outros_info" maxlength="200" name="outros_info" type="text"/>
</div>
<div class="form-group text-center">
<button class="btn btn-primary" type="submit">Enviar Requisição</button>
</div>
</form></div></div></section>
<section class="section values" id="faq"><div class="container"><div class="section-intro reveal"><span class="eyebrow">Dúvidas</span><h2>Perguntas em painéis com profundidade.</h2></div><div style="max-width:860px;margin:auto"><details class="faq-item reveal"><summary>Como posso entrar em contato com a CONFINTER?</summary><p>Utilize os canais de atendimento apresentados no site, como WhatsApp e e-mail.</p></details><details class="faq-item reveal"><summary>Posso solicitar informações sobre os serviços?</summary><p>Sim. Os canais de atendimento permitem solicitar informações e conversar com a equipe.</p></details><details class="faq-item reveal"><summary>O que é a integração IoT do Projeto Integrador VI?</summary><p>É um protótipo de Service Desk para registrar chamados e interpretar leituras enviadas por dispositivos.</p></details></div></div></section>
<section class="section testimonials" id="depoimentos"><div class="container"><div class="section-intro reveal"><span class="eyebrow">Depoimentos</span><h2>Histórias de quem confia na CONFINTER.</h2></div><div class="confinter-dynamic-reviews"><section id="depoimentos" class="testimonials">
        <div class="container" data-aos="fade-up">
            <div class="section-title">
                <h2>Depoimentos</h2>
            </div>
            <div class="testimonials-slider swiper" data-aos="fade-up" data-aos-delay="100">
                <div class="swiper-wrapper darkmode-white-text">
                    <?php
                    if (defined('CONFINTER_DB_READY') && CONFINTER_DB_READY) {
                        $sql = "SELECT nome, mensagem FROM depoimentos WHERE status_mod = 'aprovado'";
                        $result = mysqli_query($conexao, $sql);

                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $nome = !empty($row['nome']) ? htmlspecialchars((string) $row['nome'], ENT_QUOTES, 'UTF-8') : "Anônimo";
                                $mensagem = htmlspecialchars((string) ($row['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="swiper-slide">
                        <div class="testimonial-item">
                            <p>
                                <i class="bx bxs-quote-alt-left quote-icon-left"></i>
                                <?php echo $mensagem; ?>
                                <i class="bx bxs-quote-alt-right quote-icon-right"></i>
                            </p>
                            <h3><?php echo $nome; ?></h3>
                        </div>
                    </div><!-- Fim Item Depoimentos -->
                    <?php
                            }
                        } else {
                            echo "<div class='swiper-slide'><div class='testimonial-item'><p>Nenhum depoimento aprovado disponível.</p></div></div>";
                        }
                    } else {
                        $demoDepoimentos = [
                            ['nome' => 'Cliente CONFINTER', 'mensagem' => 'A nova experiência 3D deixou o atendimento mais claro e transmitiu mais confiança na marca.'],
                            ['nome' => 'Equipe do PI VI', 'mensagem' => 'O protótipo integrou design, Service Desk e IoT em uma narrativa visual coerente para demonstração acadêmica.'],
                            ['nome' => 'Visitante em demonstração', 'mensagem' => 'Mesmo sem banco configurado, foi possível navegar pelo projeto e entender o fluxo completo da solução.'],
                        ];
                        foreach ($demoDepoimentos as $demoItem) {
                    ?>
                    <div class="swiper-slide">
                        <div class="testimonial-item">
                            <p>
                                <i class="bx bxs-quote-alt-left quote-icon-left"></i>
                                <?php echo htmlspecialchars((string) $demoItem['mensagem'], ENT_QUOTES, 'UTF-8'); ?>
                                <i class="bx bxs-quote-alt-right quote-icon-right"></i>
                            </p>
                            <h3><?php echo htmlspecialchars((string) $demoItem['nome'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                    </div>
                    <?php
                        }
                    }
                    ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </section></div><div class="form-panel tilt reveal" style="margin-top:32px"><h3>Envie seu depoimento</h3><form action="php/enviar_depoimento.php" id="form-depoimento" method="POST">
<div class="form-group">
<input class="br form-control" data-msg="" data-rule="minlen:4" id="nome" name="nome" placeholder="Insira o nome, em branco enviará como Anônimo" type="text">
<div class="br validation"></div>
</input></div>
<div class="form-group">
<textarea class="br form-control" data-msg="Por favor escreva algo para nós" data-rule="required" name="mensagem" placeholder="Mensagem" rows="5"></textarea>
<!-- Adicionando um identificador único ao elemento onde a mensagem de erro será exibida -->
<div class="text-danger" id="erro-mensagem">
<?php
                                // Verificar se a variável de sessão erro_mensagem está definida
                                if (isset($_SESSION['erro_mensagem'])) {
                                    // Exibir a mensagem de erro
                                    echo htmlspecialchars((string) $_SESSION['erro_mensagem'], ENT_QUOTES, 'UTF-8');
                                    // Remover a variável de sessão para que a mensagem não seja exibida novamente após atualizar a página
                                    unset($_SESSION['erro_mensagem']);
                                }
                                ?>
</div>
<div class="br validation"></div>
<div class="en validation"></div>
</div>
<div class="form-group">
<button class="btn btn-primary" type="submit">Enviar Depoimento</button>
</div>
</form></div></div></section>
<section class="section contact" id="chegar"><div class="container two"><div class="reveal"><span class="eyebrow">Como chegar</span><h2>Estamos próximos, inclusive no digital.</h2><p class="lead">O mapa nesta prévia usa uma visualização tridimensional ilustrativa. Endereço e mapa real permanecem associados ao site original.</p><p><a class="pill" href="mailto:contato@confinter.com.br">Enviar e-mail ↗</a></p></div><div class="map-card tilt reveal"><span class="pin">📍</span><strong>CONFINTER • Localização ilustrativa</strong></div></div></section><section class="section values" id="login-demo"><div class="container reveal" style="text-align:center"><span class="eyebrow">Área restrita</span><h2>Acesso ao sistema CONFINTER</h2><p>O acesso administrativo, a autenticação e os módulos funcionais continuam no sistema PHP existente.</p><a class="pill" href="institucional-original.php">Acessar login institucional</a> <a class="pill" href="service-desk.php">Abrir Service Desk</a></div></section></main><footer>CONFINTER • Consolidando sonhos — Protótipo visual 3D para avaliação do Projeto Integrador VI. Conteúdo ilustrativo nas áreas demonstrativas.</footer>
<script>
(()=>{'use strict';const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;const $=(s)=>document.querySelector(s),$$=(s)=>[...document.querySelectorAll(s)];const slides=[{title:'Crédito para transformar <em>seus planos.</em>',sub:'Conheça soluções financeiras com proximidade, clareza e orientação.'},{title:'Proteção para o que <em>mais importa.</em>',sub:'Segurança e tranquilidade com atendimento próximo e humano.'},{title:'Atendimento presente em <em>cada etapa.</em>',sub:'Nossa equipe conecta pessoas a respostas, serviços e novas possibilidades.'}];const images=["assets/media/v7/institucional_02.webp","assets/media/v7/institucional_03.webp","assets/media/v7/institucional_04.webp","assets/media/v7/institucional_05.webp","assets/media/v7/institucional_06.webp"];let slide=0,slideTimer;function setSlide(i){slide=(i+slides.length)%slides.length;$('#hero-title').innerHTML=slides[slide].title;$('#hero-sub').textContent=slides[slide].sub;$('#hero-bg').style.backgroundImage=`url("${images[slide]}")`;$$('.dot').forEach((b,j)=>b.classList.toggle('on',j===slide));document.dispatchEvent(new CustomEvent('confinter:slidechange',{detail:{index:slide}}));}$('#prev').onclick=()=>setSlide(slide-1);$('#next').onclick=()=>setSlide(slide+1);$$('.dot').forEach(b=>b.onclick=()=>setSlide(Number(b.dataset.slide)));setSlide(0);if(!reduce){slideTimer=setInterval(()=>{if(!document.hidden)setSlide(slide+1)},6500)}
const scene=$('#scene'),hero=$('.hero');if(!reduce){hero.addEventListener('pointermove',e=>{const r=hero.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;scene.style.setProperty('--ry',`${-12+x*24}deg`);scene.style.setProperty('--rx',`${6-y*20}deg`);$('#hero-bg').style.setProperty('--bgx',`${-x*20}px`);$('#hero-bg').style.setProperty('--bgy',`${-y*18}px`)});hero.addEventListener('pointerleave',()=>{scene.style.setProperty('--ry','-12deg');scene.style.setProperty('--rx','6deg')})}
if(!reduce&&matchMedia('(hover:hover)').matches){$$('.tilt').forEach(el=>{el.addEventListener('pointermove',e=>{const r=el.getBoundingClientRect();const x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;el.style.setProperty('--tx',`${-y*11}deg`);el.style.setProperty('--ty',`${x*13}deg`)});el.addEventListener('pointerleave',()=>{el.style.setProperty('--tx','0deg');el.style.setProperty('--ty','0deg')})})}
if('IntersectionObserver' in window&&!reduce){document.body.classList.add('has-observer');const io=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target)}}),{threshold:.09,rootMargin:'0px 0px 75px 0px'});$$('.reveal').forEach(el=>io.observe(el))}
const descriptions=['O dispositivo capta uma métrica e prepara uma leitura identificada para envio.','A API recebe a telemetria, valida sua origem e registra a leitura.','O motor avalia os limites, executa regras e aplica cooldown para evitar chamados repetidos.','Uma condição crítica gera chamado vinculado ao dispositivo e à leitura de origem.','O técnico acompanha a ocorrência, registra histórico e conclui o atendimento.'];let stage=0,stageTimer=null;function setStage(n){stage=(n+5)%5;$$('[data-stage]').forEach((b,j)=>{b.classList.toggle('active',j===stage);b.setAttribute('aria-pressed',j===stage?'true':'false')});$('#stage-detail').textContent=descriptions[stage]}$$('[data-stage]').forEach(b=>b.onclick=()=>setStage(Number(b.dataset.stage)));$('#stage-back').onclick=()=>setStage(stage-1);$('#stage-next').onclick=()=>setStage(stage+1);$('#stage-play').onclick=()=>{if(stageTimer){clearInterval(stageTimer);stageTimer=null;$('#stage-play').textContent='▶ Reproduzir'}else{setStage(0);stageTimer=setInterval(()=>setStage(stage+1),1600);$('#stage-play').textContent='Ⅱ Pausar'}};
if($('#demo-form')){$('#demo-form').addEventListener('submit',e=>{e.preventDefault();const msg=$('#form-response');if(msg)msg.textContent='Demonstração visual.'})};$('#hamb').onclick=()=>{const nav=$('#nav');nav.classList.toggle('open');$('#hamb').setAttribute('aria-expanded',String(nav.classList.contains('open')))};$$('.nav a').forEach(a=>a.onclick=()=>$('#nav').classList.remove('open'));let raf=false;window.addEventListener('scroll',()=>{if(raf)return;raf=true;requestAnimationFrame(()=>{$('#progress').style.width=(100*scrollY/(document.documentElement.scrollHeight-innerHeight||1))+'%';raf=false})},{passive:true});
})();
</script><script>document.addEventListener('DOMContentLoaded',()=>{const f=document.getElementById('form-requisicao');if(f)f.addEventListener('submit',()=>{const d=document.getElementById('data_nascimento');if(d&&/^\d{2}\/\d{2}\/\d{4}$/.test(d.value)){const a=d.value.split('/');d.value=a[2]+'-'+a[1]+'-'+a[0]}})});</script></body></html>