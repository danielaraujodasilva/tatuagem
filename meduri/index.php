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
    
    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --black: #000;
            --dark: #0d0d0d;
            --gray-dark: #181818;
            --gray: #222;
            --text: #ddd;
            --gold: #d4af37;
            --gold-dark: #b8972e;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            color: var(--text);
            background: var(--dark) url('https://img.freepik.com/free-photo/black-texture_1160-804.jpg') repeat;
            background-size: 600px; /* maior pra ficar bem discreto */
            background-attachment: fixed;
        }
        
        h1, h2, h3 {
            font-family: 'Playfair Display', serif;
            color: var(--gold);
        }
        
        .navbar {
            background: rgba(0,0,0,0.95) !important;
        }
        
        .hero {
            background: linear-gradient(rgba(0,0,0,0.75), rgba(0,0,0,0.85)), url('https://amazetattoo.com/wp-content/uploads/2026/02/Japanese-Back-Tattoo-Designs.webp') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            text-align: center;
            color: white;
        }
        
        .btn-gold {
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: #000;
            border: none;
            font-weight: bold;
            border-radius: 50px;
            transition: all 0.4s ease;
        }
        
        .btn-gold:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(212,175,55,0.25);
        }
        
        .card {
            background: var(--gray-dark);
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: transform 0.4s, box-shadow 0.4s;
            box-shadow: 0 8px 20px rgba(0,0,0,0.6);
        }
        
        .card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.7);
        }
        
        /* Promo cards */
        .promo-card {
            position: relative;
            height: 400px;
            background-size: cover;
            background-position: center;
            transition: all 0.5s;
            color: white;
            text-shadow: 0 2px 5px rgba(0,0,0,0.8);
        }
        
        .promo-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.4), rgba(0,0,0,0.7));
            transition: opacity 0.5s;
        }
        
        .promo-card:hover::before {
            opacity: 0.3;
        }
        
        .promo-card img.bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(100%);
            transition: filter 0.6s ease;
        }
        
        .promo-card:hover img.bg-img {
            filter: grayscale(0%);
        }
        
        .promo-content {
            position: relative;
            z-index: 2;
            padding: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        /* Equipe - cards mais finos/retangulares verticais */
        .team-card {
            height: 580px; /* mais alto e fino */
            background: var(--gray);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .team-img {
            width: 100%;
            height: 75%; /* ocupa mais espaço vertical */
            object-fit: cover;
            object-position: center top; /* foca no rosto, preenche sem distorcer */
        }
        
        .team-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.75);
            opacity: 0;
            transition: opacity 0.4s;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 20px;
            color: white;
            text-align: center;
        }
        
        .team-card:hover .team-overlay {
            opacity: 1;
        }
        
        .carousel .carousel-item .row > div {
            padding: 0 10px;
        }
        
        footer {
            background: #000;
            padding: 60px 0 30px;
            border-top: 1px solid #222;
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #25D366;
            color: white;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            z-index: 1000;
            transition: transform 0.3s;
        }
        
        .whatsapp-float:hover { transform: scale(1.15); }
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
                    <li class="nav-item"><a class="nav-link" href="#promo">Ofertas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#equipe">Equipe</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfólio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contato">Contato</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero" data-aos="fade-up">
        <div class="container">
            <h1 class="display-3 fw-bold mb-4">Ankh Tattoo</h1>
            <p class="lead fs-4 mb-5">Fechamentos em uma sessão • Tatuagens gigantes • Arte que marca pra sempre</p>
            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="btn btn-gold btn-lg px-5 py-3">Agende Agora</a>
        </div>
    </section>

    <!-- Ofertas -->
    <section id="promo" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Ofertas Ativas</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="promo-card rounded-4 shadow">
                        <img class="bg-img" src="https://cdn2.fabbon.com/uploads/image/file/36671/realism-back-tattoo.webp" alt="Fechamento costas">
                        <div class="promo-content">
                            <h3>Fechamento Completo</h3>
                            <p class="fs-1 fw-bold">R$699</p>
                            <p>Costas, braço ou perna em uma sessão</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20fechamento%20R$699" class="btn btn-gold mt-3">Quero</a>
                        </div>
                    </div>
                </div>
                <!-- Os outros dois promo cards iguais da versão anterior -->
                <div class="col-md-4">
                    <div class="promo-card rounded-4 shadow">
                        <img class="bg-img" src="https://images.pexels.com/photos/3327153/pexels-photo-3327153.jpeg?auto=compress&cs=tinysrgb&h=627&fit=crop&w=1200" alt="Tatuador">
                        <div class="promo-content">
                            <h3>Mês da Mulher</h3>
                            <p class="fs-1 fw-bold">R$499</p>
                            <p>Qualquer fechamento feminino</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20fechamento%20feminino" class="btn btn-gold mt-3">Aproveitar</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="promo-card rounded-4 shadow">
                        <img class="bg-img" src="https://images.pexels.com/photos/10613958/pexels-photo-10613958.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1" alt="Máquina de tatuagem">
                        <div class="promo-content">
                            <h3>Floral Especial</h3>
                            <p class="fs-1 fw-bold">R$399</p>
                            <p>Fechamento floral feminino</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20floral%20R$399" class="btn btn-gold mt-3">Quero!</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Equipe - Carousel com mais itens, cards finos -->
    <section id="equipe" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Nossa Equipe</h2>
            <div id="teamCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <!-- Slide 1 -->
                    <div class="carousel-item active">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img" alt="Tatuador 1">
                                    <div class="team-overlay">
                                        <h4>Meduri</h4>
                                        <p>Realismo & Fechamentos Gigantes<br>+10 anos | Especialista em sessões únicas</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://images.squarespace-cdn.com/content/v1/5c7ca27a01232c45a11a3f4f/f854edd9-6d94-469d-9275-a22777ea7b63/0C9A5611.jpg" class="team-img" alt="Tatuador 2">
                                    <div class="team-overlay">
                                        <h4>Lucas Black</h4>
                                        <p>Blackwork & Geométrico<br>Peças impactantes e minimalistas</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://thumbs.dreamstime.com/b/delicate-fine-line-tattoo-blooming-flowers-woman-back-subtle-ink-shading-creates-depth-skin-minimalist-black-white-403847740.jpg" class="team-img" alt="Tatuador 3">
                                    <div class="team-overlay">
                                        <h4>Ana Fine</h4>
                                        <p>Fine Line & Floral<br>Especial em femininas delicadas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="carousel-item">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img" alt="Tatuador 4">
                                    <div class="team-overlay">
                                        <h4>João Old</h4>
                                        <p>Old School & Tradicional<br>Clássicos com twist moderno</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://images.squarespace-cdn.com/content/v1/5c7ca27a01232c45a11a3f4f/f854edd9-6d94-469d-9275-a22777ea7b63/0C9A5611.jpg" class="team-img" alt="Tatuador 5">
                                    <div class="team-overlay">
                                        <h4>Carla Neo</h4>
                                        <p>Neo Tradicional & Color<br>Cores vibrantes e narrativas</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://thumbs.dreamstime.com/b/delicate-fine-line-tattoo-blooming-flowers-woman-back-subtle-ink-shading-creates-depth-skin-minimalist-black-white-403847740.jpg" class="team-img" alt="Tatuador 6">
                                    <div class="team-overlay">
                                        <h4>Rafael Dot</h4>
                                        <p>Dotwork & Mandalas<br>Detalhes infinitos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 3 -->
                    <div class="carousel-item">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img" alt="Tatuador 7">
                                    <div class="team-overlay">
                                        <h4>Sofia Realism</h4>
                                        <p>Realismo Preto e Cinza<br>Retratos e animais</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://images.squarespace-cdn.com/content/v1/5c7ca27a01232c45a11a3f4f/f854edd9-6d94-469d-9275-a22777ea7b63/0C9A5611.jpg" class="team-img" alt="Tatuador 8">
                                    <div class="team-overlay">
                                        <h4>Victor Cover</h4>
                                        <p>Cover-ups & Reworks<br>Transforma o antigo em novo</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="team-card shadow">
                                    <img src="https://thumbs.dreamstime.com/b/delicate-fine-line-tattoo-blooming-flowers-woman-back-subtle-ink-shading-creates-depth-skin-minimalist-black-white-403847740.jpg" class="team-img" alt="Tatuador 9">
                                    <div class="team-overlay">
                                        <h4>Maria Script</h4>
                                        <p>Lettering & Caligrafia<br>Frases e poemas na pele</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#teamCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#teamCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Portfólio (exemplo simples, expanda se quiser) -->
    <section id="portfolio" class="py-5 bg-black">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Portfólio</h2>
            <p class="text-center lead mb-5">Peças impactantes – especialista em gigantismo e fechamentos rápidos</p>
            <!-- Adicione a galeria aqui como nas versões anteriores -->
            <div class="row g-4">
                <!-- Exemplo: 3 imagens -->
                <div class="col-md-4"><img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" class="img-fluid rounded" alt="Exemplo 1"></div>
                <div class="col-md-4"><img src="https://primitivetattoobali.com/wp-content/uploads/2025/07/Primitive-Tattoo-Ink-Portfolio.webp" class="img-fluid rounded" alt="Exemplo 2"></div>
                <div class="col-md-4"><img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-full-sleeve.png" class="img-fluid rounded" alt="Exemplo 3"></div>
            </div>
        </div>
    </section>

    <!-- FAQ (exemplo curto) -->
    <section id="faq" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Perguntas Frequentes</h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                            Quanto tempo leva um fechamento?
                        </button>
                    </h2>
                    <div id="q1" class="accordion-collapse collapse show">
                        <div class="accordion-body">Depende do tamanho, mas muitos em uma única sessão longa.</div>
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

    <!-- Footer -->
    <footer class="text-center text-lg-start">
        <div class="container">
            <div class="row py-4">
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Ankh Tattoo</h5>
                    <p>Arte na pele com qualidade e higiene. Especialista em grandes peças.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#promo" class="text-light">Ofertas</a></li>
                        <li><a href="#equipe" class="text-light">Equipe</a></li>
                        <li><a href="#portfolio" class="text-light">Portfólio</a></li>
                        <li><a href="https://www.instagram.com/ankh_tattoo/" class="text-light">Instagram</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Contato</h5>
                    <p>Av. do Taboão, 3802<br>São Bernardo do Campo - SP</p>
                    <p>WhatsApp: <a href="https://api.whatsapp.com/send?phone=5511968699109" class="text-light">11 96869-9109</a></p>
                </div>
            </div>
            <hr class="border-light">
            <p class="mb-0 py-3">&copy; 2026 Ankh Tattoo. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- WhatsApp Float -->
    <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="whatsapp-float">
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