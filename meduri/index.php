<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ankh Tattoo - Tatuagens Gigantes & Fechamentos em Uma Sessão | São Bernardo do Campo</title>
    <meta name="description" content="Especialista em fechamentos em uma única sessão e tatuagens gigantes. Promoções Mês da Mulher: floral por R$399! Agende agora.">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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
            background: var(--dark) url('https://img.freepik.com/free-vector/abstract-background-with-dark-square-pattern_848876.jpg') repeat;
            background-size: 800px; /* discreto, low contrast squares */
            background-attachment: fixed;
        }
        
        h1, h2, h3 { font-family: 'Playfair Display', serif; color: var(--gold); }
        
        .navbar { background: rgba(0,0,0,0.95) !important; }
        
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
        
        .btn-gold:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(212,175,55,0.25); }
        
        .card { background: var(--gray-dark); border: none; border-radius: 20px; overflow: hidden; transition: transform 0.4s, box-shadow 0.4s; box-shadow: 0 8px 20px rgba(0,0,0,0.6); }
        
        .card:hover { transform: translateY(-12px); box-shadow: 0 20px 40px rgba(0,0,0,0.7); }
        
        /* Promo & Portfolio hover B&W -> color */
        .hover-img-container {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            cursor: pointer;
        }
        
        .hover-img {
            width: 100%;
            height: 400px; /* padronizado */
            object-fit: cover;
            filter: grayscale(100%);
            transition: filter 0.6s ease;
        }
        
        .hover-img-container:hover .hover-img { filter: grayscale(0%); }
        
        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.6);
            opacity: 0;
            transition: opacity 0.4s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 20px;
        }
        
        .hover-img-container:hover .overlay { opacity: 1; }
        
        /* Equipe cards finos */
        .team-card {
            height: 620px; /* mais fino/alto */
            background: var(--gray);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .team-img {
            width: 100%;
            height: 78%;
            object-fit: cover;
            object-position: center top;
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
            padding: 25px;
            color: white;
            text-align: center;
        }
        
        .team-card:hover .team-overlay { opacity: 1; }
        
        .carousel .carousel-item .row > div { padding: 0 12px; }
        
        footer { background: #000; padding: 60px 0 30px; border-top: 1px solid #222; }
        
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

    <!-- Ofertas (mantido) -->
    <section id="promo" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Ofertas Ativas</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="hover-img-container shadow" data-bs-toggle="modal" data-bs-target="#modalPromo1">
                        <img class="hover-img" src="https://cdn2.fabbon.com/uploads/image/file/36671/realism-back-tattoo.webp" alt="Fechamento costas">
                        <div class="overlay">
                            <h4>Fechamento Completo</h4>
                            <p class="fs-3 fw-bold">R$699</p>
                            <p>Costas, braço ou perna em uma sessão</p>
                        </div>
                    </div>
                </div>
                <!-- Repita para os outros dois com modais se quiser, ou mantenha simples -->
                <!-- ... adicione os outros promo cards com mesma estrutura ... -->
            </div>
        </div>
    </section>

    <!-- Equipe - Carousel auto a cada 3s -->
    <section id="equipe" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Nossa Equipe</h2>
            <div id="teamCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="row">
                            <div class="col-md-4"><div class="team-card shadow">
                                <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img" alt="Meduri">
                                <div class="team-overlay">
                                    <h4>Meduri</h4>
                                    <p>Realismo & Fechamentos Gigantes<br>+10 anos | Sessões únicas</p>
                                </div>
                            </div></div>
                            <!-- Repita para mais 2 no slide -->
                            <!-- ... adicione os outros como no anterior ... -->
                        </div>
                    </div>
                    <!-- Mais carousel-item com 3 cards cada -->
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

    <!-- Portfólio com hover e modal -->
    <section id="portfolio" class="py-5 bg-black">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Portfólio</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="hover-img-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort1">
                        <img class="hover-img" src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" alt="Sleeve de perna">
                        <div class="overlay">
                            <h5>Sleeve Biomecânico</h5>
                            <p>18h total | Realismo futurista</p>
                        </div>
                    </div>
                </div>
                <!-- Repita para mais imagens com modais diferentes -->
                <!-- Exemplo modal abaixo -->
            </div>
        </div>
    </section>

    <!-- Exemplo de Modal para Portfólio (repita com ids diferentes) -->
    <div class="modal fade" id="modalPort1" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-gold">Sleeve Biomecânico de Perna</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" class="img-fluid rounded mb-3" alt="Detalhe">
                    <p>Estilo biomecânico com detalhes metálicos e orgânicos. Feito em várias sessões, mas adaptável para fechamento. Tempo estimado: 18 horas.</p>
                    <p><strong>Estilo:</strong> Realismo futurista | <strong>Cor:</strong> Preto e cinza</p>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ, Contato, Footer (mantidos da versão anterior) -->
    <!-- ... cole aqui se precisar ... -->

    <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="whatsapp-float">
        <i class="fab fa-whatsapp"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
        // Força autoplay do carousel a cada 3s (3000ms)
        var myCarousel = document.querySelector('#teamCarousel');
        var carousel = new bootstrap.Carousel(myCarousel, {
            interval: 3000,
            ride: 'carousel'
        });
    </script>
</body>
</html>