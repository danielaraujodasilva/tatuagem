<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ankh Tattoo - Tatuagens Gigantes & Fechamentos em Uma Sessão | São Bernardo do Campo</title>
    <meta name="description" content="Especialista em fechamentos em uma única sessão e tatuagens gigantes. Promoções Mês da Mulher: floral por R$399! Agende agora.">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- AOS Animate On Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --black: #000;
            --dark: #111;
            --dark-gray: #1a1a1a;
            --gray: #222;
            --light-gray: #aaa;
            --gold: #FFD700;
            --gold-dark: #D4AF37;
            --text: #e0e0e0;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            color: var(--text);
            background: linear-gradient(to bottom, #000, #111);
            background-attachment: fixed;
        }
        
        h1, h2, h3 {
            font-family: 'Playfair Display', serif;
            color: var(--gold);
        }
        
        .navbar {
            background: rgba(0,0,0,0.9) !important;
            backdrop-filter: blur(10px);
        }
        
        .hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.8)), url('https://thumbs.dreamstime.com/b/intricate-floral-tattoo-design-covers-back-man-indoor-setting-soft-evening-light-s-bare-showcases-vibrant-386266844.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.4), rgba(26,26,26,0.6));
        }
        
        .btn-gold {
            background: var(--gold);
            color: #000;
            border: none;
            font-weight: bold;
            transition: all 0.3s ease;
            border-radius: 50px;
        }
        
        .btn-gold:hover {
            background: var(--gold-dark);
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(255,215,0,0.3);
        }
        
        .card {
            background: var(--dark-gray);
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.4s, box-shadow 0.4s;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        }
        
        .card:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 50px rgba(255,215,0,0.15);
        }
        
        .promo-card {
            background: linear-gradient(135deg, #111, #222);
        }
        
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            cursor: pointer;
        }
        
        .gallery-img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        
        .gallery-item:hover .gallery-img {
            transform: scale(1.12);
        }
        
        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            color: white;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.4s;
        }
        
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        
        .modal-content {
            background: #111;
            color: #eee;
            border: 1px solid var(--gold);
            border-radius: 15px;
        }
        
        .accordion-button {
            background: var(--gray) !important;
            color: var(--gold) !important;
        }
        
        .accordion-body {
            background: var(--dark-gray);
            color: #eee;
        }
        
        footer {
            background: #000;
            padding: 60px 0 30px;
            border-top: 1px solid #222;
        }
        
        .footer-link {
            color: var(--light-gray);
            transition: color 0.3s;
        }
        
        .footer-link:hover {
            color: var(--gold);
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #25D366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s;
        }
        
        .whatsapp-float:hover {
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .gallery-img { height: 250px; }
        }
    </style>
</head>
<body data-bs-spy="scroll" data-bs-target="#navbar" data-bs-offset="70">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="#">Ankh Tattoo</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#promo">Promoções</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfólio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#sobre">Sobre</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contato">Contato</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" data-aos="fade-up">
        <div class="container position-relative z-1">
            <h1 class="display-3 fw-bold mb-4">Ankh Tattoo</h1>
            <p class="lead fs-4 mb-5">Fechamentos em uma sessão • Peças gigantes • Arte eterna na pele</p>
            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar%20minha%20tatuagem" class="btn btn-gold btn-lg px-5 py-3">Agende Agora</a>
        </div>
    </section>

    <!-- Promoções -->
    <section id="promo" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Ofertas Ativas</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card promo-card text-center p-4">
                        <h3>Fechamento Completo</h3>
                        <p class="fs-1 fw-bold">R$699</p>
                        <p>Costas, braço ou perna – uma sessão só</p>
                        <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20fechamento%20por%20R$699" class="btn btn-gold mt-3">Quero</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card promo-card text-center p-4">
                        <h3>Mês da Mulher</h3>
                        <p class="fs-1 fw-bold">R$499</p>
                        <p>Qualquer fechamento feminino</p>
                        <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20fechamento%20feminino" class="btn btn-gold mt-3">Aproveitar</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card promo-card text-center p-4">
                        <h3>Floral Especial</h3>
                        <p class="fs-1 fw-bold">R$399</p>
                        <p>Fechamento floral feminino</p>
                        <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20floral%20por%20R$399" class="btn btn-gold mt-3">Quero!</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Portfólio -->
    <section id="portfolio" class="py-5 bg-black">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Portfólio</h2>
            <p class="text-center lead mb-5">Peças impactantes – especialista em gigantismo e fechamentos rápidos</p>
            <div class="row g-4">
                <!-- Imagens com overlay e modal -->
                <div class="col-md-4">
                    <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#modal1">
                        <img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" class="gallery-img" alt="Sleeve de perna gigante">
                        <div class="gallery-overlay">
                            <h5>Sleeve de Perna Gigante</h5>
                            <p>Biomecânico realista – 18h total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#modal2">
                        <img src="https://primitivetattoobali.com/wp-content/uploads/2025/07/Primitive-Tattoo-Ink-Portfolio.webp" class="gallery-img" alt="Fechamento de braço samurai">
                        <div class="gallery-overlay">
                            <h5>Fechamento Samurai</h5>
                            <p>Braço completo em uma sessão – estilo japonês</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#modal3">
                        <img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-full-sleeve.png" class="gallery-img" alt="Sleeve realista com elementos mistos">
                        <div class="gallery-overlay">
                            <h5>Sleeve Realista Misto</h5>
                            <p>Figura humana + animal – contraste alto</p>
                        </div>
                    </div>
                </div>
                <!-- Mais itens... adicione conforme quiser -->
            </div>
        </div>
    </section>

    <!-- Modals para imagens grandes -->
    <div class="modal fade" id="modal1" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-gold">Sleeve de Perna Gigante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" class="img-fluid rounded" alt="Detalhe da tatuagem">
                    <p class="mt-3">Biomecânico com detalhes realistas. Feito em várias sessões, mas possível fechamento adaptado.</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Repita modais para modal2, modal3 etc. com srcs diferentes -->

    <!-- Sobre -->
    <section id="sobre" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Sobre Nós</h2>
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="lead">Anos de experiência em tatuagens grandes e fechamentos eficientes. Higiene rigorosa, ambiente dark e foco total no seu projeto.</p>
                    <p>Estúdio em São Bernardo do Campo – arte que dura pra sempre.</p>
                </div>
                <div class="col-md-6">
                    <img src="https://thumbs.dreamstime.com/b/modern-tattoo-studio-background-pink-lights-underground-style-360511234.jpg" class="img-fluid rounded shadow" alt="Interior do estúdio">
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Perguntas Frequentes</h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item bg-transparent border-0 mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                            Quanto tempo leva um fechamento?
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse">
                        <div class="accordion-body">Depende do tamanho, mas muitos são feitos em uma única sessão longa.</div>
                    </div>
                </div>
                <!-- Adicione mais -->
            </div>
        </div>
    </section>

    <!-- Contato -->
    <section id="contato" class="py-5 text-center" data-aos="fade-up">
        <div class="container">
            <h2 class="mb-4">Pronto pra sua próxima peça?</h2>
            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="btn btn-gold btn-lg px-5 py-3">
                <i class="fab fa-whatsapp me-2"></i> Falar Agora
            </a>
            <p class="mt-4">Av. do Taboão, 3802 - São Bernardo do Campo</p>
        </div>
    </section>

    <!-- Footer bonito -->
    <footer class="text-center text-lg-start">
        <div class="container">
            <div class="row py-4">
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Ankh Tattoo</h5>
                    <p>Especialista em tatuagens gigantes e fechamentos em uma sessão. Arte com qualidade e higiene.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="#promo" class="footer-link">Promoções</a></li>
                        <li><a href="#portfolio" class="footer-link">Portfólio</a></li>
                        <li><a href="#faq" class="footer-link">FAQ</a></li>
                        <li><a href="https://www.instagram.com/ankh_tattoo/" target="_blank" class="footer-link">Instagram</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Contato</h5>
                    <p>Av. do Taboão, 3802<br>São Bernardo do Campo - SP</p>
                    <p>WhatsApp: <a href="https://api.whatsapp.com/send?phone=5511968699109" class="footer-link">11 96869-9109</a></p>
                    <p>Seg-Sex: 10h às 20h | Sáb: 10h às 18h</p>
                </div>
            </div>
            <hr class="border-light-gray">
            <p class="mb-0 py-3">&copy; 2026 Ankh Tattoo. Todos os direitos reservados. Feito com arte e respeito à pele.</p>
        </div>
    </footer>

    <!-- WhatsApp Float -->
    <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar%20uma%20tatuagem" class="whatsapp-float">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
    </script>
</body>
</html>