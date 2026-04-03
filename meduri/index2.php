<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ankh Tattoo - Tatuagens Gigantes & Fechamentos em Uma Sessão | São Bernardo do Campo</title>
    <meta name="description" content="Especialista em fechamentos em uma única sessão e tatuagens gigantes. Promoções exclusivas para o mês da mulher: qualquer fechamento feminino por R$499, floral por R$399! Agende agora via WhatsApp.">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css">
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
            --gold: #c9a96e;
            --gold-dark: #a07d3f;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            color: var(--text);
            background: var(--dark) url('https://img.freepik.com/free-vector/abstract-background-with-dark-square-pattern_848876.jpg') repeat;
            background-size: 600px;
            background-attachment: fixed;
        }
        
        h1, h2, h3 { font-family: 'Playfair Display', serif; color: var(--gold); }
        
        .navbar { background: rgba(0,0,0,0.95) !important; }
        
        .hero {
            background: linear-gradient(rgba(0,0,0,0.85), rgba(0,0,0,0.9)), url('https://amazetattoo.com/wp-content/uploads/2026/02/Japanese-Back-Tattoo-Designs.webp') center/cover no-repeat fixed;
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
        
        .btn-gold:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(201,169,110,0.3); }
        
        .card { background: var(--gray-dark); border: none; border-radius: 20px; overflow: hidden; transition: transform 0.4s, box-shadow 0.4s; box-shadow: 0 8px 20px rgba(0,0,0,0.6); }
        
        .card:hover { transform: translateY(-12px); box-shadow: 0 20px 40px rgba(0,0,0,0.7); }
        
        .hover-container {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            cursor: pointer;
        }
        
        .hover-img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            filter: grayscale(100%);
            transition: filter 0.6s ease;
        }
        
        .hover-container:hover .hover-img { filter: grayscale(0%); }
        
        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.65);
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
        
        .hover-container:hover .overlay { opacity: 1; }
        
        .team-card {
            height: 620px;
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
        
        .swiper {
            width: 100%;
            padding: 20px 0;
        }
        
        .swiper-slide {
            display: flex;
            justify-content: center;
        }
        
        .swiper-button-next, .swiper-button-prev {
            color: var(--gold) !important;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }
        
        .swiper-button-next:after, .swiper-button-prev:after {
            font-size: 20px;
        }
        
        .testimonial-card {
            background: var(--gray-dark);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        
        footer { background: #000; padding: 60px 0 30px; border-top: 1px solid #222; }
        
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #25D366;
            color: white;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            font-size: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6);
            z-index: 1000;
            transition: transform 0.3s;
            animation: pulse 1.5s infinite;
        }
        
        .whatsapp-float:hover { transform: scale(1.15); }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); }
            70% { box-shadow: 0 0 0 15px rgba(37,211,102,0); }
            100% { box-shadow: 0 0 0 0 rgba(37,211,102,0); }
        }
        /* ===== CARROSSEL EQUIPE ===== */
.promo-col {
  min-width: 260px;
  margin: 10px;
  cursor: pointer;
}

.team-img {
  width: 100%;
  height: 350px;
  object-fit: cover;
  filter: grayscale(100%);
  transition: 0.3s;
  border-radius: 15px;
}

.promo-col:hover .team-img {
  filter: grayscale(0%);
}

/* BOTÕES */
.carousel-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0,0,0,0.7);
  border: none;
  color: #fff;
  padding: 10px 15px;
  cursor: pointer;
  z-index: 2;
}

.carousel-btn.prev { left: 0; }
.carousel-btn.next { right: 0; }

/* MODAL */
.modal-equipe {
  display: none;
  position: fixed;
  z-index: 999;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.85);
  padding-top: 100px;
}

.modal-content-equipe {
  background: #111;
  margin: auto;
  padding: 20px;
  max-width: 500px;
  border-radius: 10px;
  color: #fff;
}

.fechar {
  float: right;
  font-size: 28px;
  cursor: pointer;
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
                    <li class="nav-item"><a class="nav-link" href="#promo">Ofertas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#equipe">Equipe</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfólio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#depoimentos">Depoimentos</a></li>
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
            <p class="lead fs-4 mb-5">Fechamentos em uma única sessão e tatuagens gigantes. Promoções limitadas – agende agora!</p>
            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="btn btn-gold btn-lg px-5 py-3">Agende Agora <i class="fab fa-whatsapp ms-2"></i></a>
        </div>
    </section>

    <!-- Ofertas -->
    <section id="promo" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Ofertas Ativas <small class="text-muted">(Válidas até 31/03/2026 – Vagas limitadas!)</small></h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="hover-container shadow">
                        <img class="hover-img" src="https://cdn2.fabbon.com/uploads/image/file/36671/realism-back-tattoo.webp" alt="Fechamento de costas realista" loading="lazy">
                        <div class="overlay">
                            <h4>Fechamento Completo</h4>
                            <p class="fs-3 fw-bold">R$699</p>
                            <p>Costas, braço ou perna em uma sessão</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20fechamento%20R$699" class="btn btn-gold mt-3">Quero</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow">
                        <img class="hover-img" src="https://images.pexels.com/photos/3327153/pexels-photo-3327153.jpeg?auto=compress&cs=tinysrgb&h=627&fit=crop&w=1200" alt="Tatuagem feminina" loading="lazy">
                        <div class="overlay">
                            <h4>Mês da Mulher</h4>
                            <p class="fs-3 fw-bold">R$499</p>
                            <p>Qualquer fechamento feminino</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20fechamento%20feminino" class="btn btn-gold mt-3">Aproveitar</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow">
                        <img class="hover-img" src="https://images.pexels.com/photos/10613958/pexels-photo-10613958.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1" alt="Tatuagem floral" loading="lazy">
                        <div class="overlay">
                            <h4>Floral Especial</h4>
                            <p class="fs-3 fw-bold">R$399</p>
                            <p>Fechamento floral feminino</p>
                            <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Quero%20o%20floral%20R$399" class="btn btn-gold mt-3">Quero!</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


  <!-- Equipe com carrossel estilo promo -->
<section id="equipe" class="py-5">
  <div class="container" data-aos="fade-up">
    
    <h2 class="text-center mb-5">Nossa Equipe</h2>

    <div class="promo-carousel-wrapper position-relative">
      <button class="carousel-btn prev">&lt;</button>

      <div class="promo-carousel overflow-hidden">
        <div class="promo-track d-flex">

          <!-- CARD -->
          <div class="promo-col team-card" data-title="Meduri" data-content="<p>Especialista em realismo e fechamentos gigantes. +12 anos de experiência.</p>">
            <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img">
            <span>Meduri</span>
          </div>

          <div class="promo-col team-card" data-title="Lucas Black" data-content="<p>Blackwork e geométrico. Minimalista e impactante.</p>">
            <img src="https://images.squarespace-cdn.com/content/v1/5c7ca27a01232c45a11a3f4f/f854edd9-6d94-469d-9275-a22777ea7b63/0C9A5611.jpg" class="team-img">
            <span>Lucas</span>
          </div>

          <div class="promo-col team-card" data-title="Ana Fine" data-content="<p>Fine line e floral. Delicadeza absurda.</p>">
            <img src="https://thumbs.dreamstime.com/b/delicate-fine-line-tattoo-blooming-flowers-woman-back-subtle-ink-shading-creates-depth-skin-minimalist-black-white-403847740.jpg" class="team-img">
            <span>Ana</span>
          </div>

          <!-- DUPLICAÇÃO PRA LOOP -->
          <div class="promo-col team-card" data-title="Meduri" data-content="<p>Especialista em realismo e fechamentos gigantes.</p>">
            <img src="https://assets.lummi.ai/assets/QmbwsRrUcUVDuoLeVqp4iDNgLQGtAxyBA1yEpNqSTR3gBd" class="team-img">
            <span>Meduri</span>
          </div>

          <div class="promo-col team-card" data-title="Lucas Black" data-content="<p>Blackwork e geométrico.</p>">
            <img src="https://images.squarespace-cdn.com/content/v1/5c7ca27a01232c45a11a3f4f/f854edd9-6d94-469d-9275-a22777ea7b63/0C9A5611.jpg" class="team-img">
            <span>Lucas</span>
          </div>

        </div>
      </div>

      <button class="carousel-btn next">&gt;</button>
    </div>

  </div>
</section>

<!-- MODAL -->
<div id="modalEquipe" class="modal-equipe">
  <div class="modal-content-equipe">
    <span class="fechar">&times;</span>
    <h3 id="modalTitulo"></h3>
    <div id="modalTexto"></div>
  </div>
</div>

    <!-- Portfólio com 6 imagens -->
    <section id="portfolio" class="py-5 bg-black">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Portfólio</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort1">
                        <img class="hover-img" src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" alt="Sleeve de perna biomecânico realista" loading="lazy">
                        <div class="overlay">
                            <h5>Sleeve Biomecânico</h5>
                            <p>Realismo futurista | ~18h</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort2">
                        <img class="hover-img" src="https://primitivetattoobali.com/wp-content/uploads/2025/07/Primitive-Tattoo-Ink-Portfolio.webp" alt="Fechamento de braço samurai" loading="lazy">
                        <div class="overlay">
                            <h5>Fechamento Samurai</h5>
                            <p>Estilo japonês | Uma sessão</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort3">
                        <img class="hover-img" src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-full-sleeve.png" alt="Sleeve realista misto" loading="lazy">
                        <div class="overlay">
                            <h5>Sleeve Realista Misto</h5>
                            <p>Figura + animal | Contraste alto</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort4">
                        <img class="hover-img" src="https://tattoos.gallery/unifytattoofl.com/images/gallery/Fairy%20Mermaid%20Forest%20Tattoo.jpg" alt="Tatuagem de floresta mágica" loading="lazy">
                        <div class="overlay">
                            <h5>Floresta Mágica</h5>
                            <p>Colorido detalhado | ~15h</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort5">
                        <img class="hover-img" src="https://tattoos.gallery/soringabor.com/images/gallery/bio%20oni%20Sorin.jpg" alt="Fechamento bio orgânico" loading="lazy">
                        <div class="overlay">
                            <h5>Bio Orgânico</h5>
                            <p>Estilo orgânico | Uma sessão</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hover-container shadow" data-bs-toggle="modal" data-bs-target="#modalPort6">
                        <img class="hover-img" src="https://images.stockcake.com/public/0/0/d/00dcdec4-d941-458f-8128-638c8191196c/sacred-golden-ankh-stockcake.jpg" alt="Tatuagem simbólica Ankh" loading="lazy">
                        <div class="overlay">
                            <h5>Ankh Sagrado</h5>
                            <p>Simbolismo dourado | ~10h</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modais Portfólio (exemplo para o primeiro, copie e mude id/src para os outros) -->
    <div class="modal fade" id="modalPort1" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-gold">Sleeve Biomecânico de Perna</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="https://inknationstudio.com/wp-content/uploads/2025/07/thumbnail-leg.png" class="img-fluid rounded mb-3" alt="Detalhe sleeve biomecânico" loading="lazy">
                    <p>Estilo biomecânico com detalhes metálicos e orgânicos. Adaptável para fechamento em sessão única.</p>
                    <p><strong>Estilo:</strong> Realismo futurista | <strong>Tempo:</strong> ~18 horas | <strong>Preço estimado:</strong> R$1200</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Depoimentos com link Instagram -->
    <section id="depoimentos" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">O que nossos clientes dizem</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p>"Fechamento incrível em uma sessão só! Meduri é gênio."</p>
                        <p class="text-end mb-0"><a href="https://www.instagram.com/clienteexemplo1/" target="_blank">@clienteexemplo1</a></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p>"A promo do mês da mulher foi perfeita pra minha floral. Recomendo demais!"</p>
                        <p class="text-end mb-0"><a href="https://www.instagram.com/clienteexemplo2/" target="_blank">@clienteexemplo2</a></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p>"Equipe profissional, higiene top e resultado absurdo. Volto com certeza."</p>
                        <p class="text-end mb-0"><a href="https://www.instagram.com/clienteexemplo3/" target="_blank">@clienteexemplo3</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Perguntas Frequentes</h2>
            <div class="accordion accordion-flush" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushOne">
                            Quanto tempo leva um fechamento?
                        </button>
                    </h2>
                    <div id="flushOne" class="accordion-collapse collapse">
                        <div class="accordion-body">Depende do tamanho e complexidade, mas muitos são feitos em uma única sessão longa.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushTwo">
                            Vocês aceitam ideias próprias?
                        </button>
                    </h2>
                    <div id="flushTwo" class="accordion-collapse collapse">
                        <div class="accordion-body">Sim! Personalizamos 100% conforme sua visão ou referência.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushThree">
                            Dói muito um fechamento grande?
                        </button>
                    </h2>
                    <div id="flushThree" class="accordion-collapse collapse">
                        <div class="accordion-body">Varia de pessoa para pessoa, mas usamos técnicas para minimizar o desconforto.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushFour">
                            Posso levar acompanhante?
                        </button>
                    </h2>
                    <div id="flushFour" class="accordion-collapse collapse">
                        <div class="accordion-body">Sim, desde que siga as normas de higiene do estúdio.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushFive">
                            Aceitam cartão ou parcelado?
                        </button>
                    </h2>
                    <div id="flushFive" class="accordion-collapse collapse">
                        <div class="accordion-body">Sim, consulte as opções diretamente no WhatsApp.</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flushSix">
                            Como cuidar da tatuagem depois?
                        </button>
                    </h2>
                    <div id="flushSix" class="accordion-collapse collapse">
                        <div class="accordion-body">Mantenha limpa, use pomada recomendada e evite sol. Damos instruções completas no agendamento.</div>
                    </div>
                </div>
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
                    <p>Especialista em tatuagens gigantes e fechamentos em uma sessão. Arte com qualidade e higiene.</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="#promo" class="text-light">Ofertas</a></li>
                        <li><a href="#equipe" class="text-light">Equipe</a></li>
                        <li><a href="#portfolio" class="text-light">Portfólio</a></li>
                        <li><a href="https://www.instagram.com/ankh_tattoo/" target="_blank" class="text-light">Instagram</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="text-gold">Contato</h5>
                    <p>Av. do Taboão, 3802<br>São Bernardo do Campo - SP</p>
                    <p>WhatsApp: <a href="https://api.whatsapp.com/send?phone=5511968699109" class="text-light">11 96869-9109</a></p>
                    <p>Seg-Sex: 10h às 20h | Sáb: 10h às 18h</p>
                </div>
            </div>
            <hr class="border-light">
            <p class="mb-0 py-3">&copy; 2026 Ankh Tattoo. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- WhatsApp Float -->
    <a href="https://api.whatsapp.com/send?phone=5511968699109&text=Oi!%20Quero%20agendar" class="whatsapp-float" title="Fale agora">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });

        const equipeSwiper = new Swiper('.equipe-swiper', {
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            slidesPerView: 1,
            spaceBetween: 20,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                768: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
            },
        });
    </script>
    <script>
// CARROSSEL
const track = document.querySelector('.promo-track');
const prev = document.querySelector('.prev');
const next = document.querySelector('.next');

let position = 0;

next.onclick = () => {
  position -= 280;
  track.style.transform = `translateX(${position}px)`;
};

prev.onclick = () => {
  position += 280;
  track.style.transform = `translateX(${position}px)`;
};

// MODAL
const modal = document.getElementById("modalEquipe");
const titulo = document.getElementById("modalTitulo");
const texto = document.getElementById("modalTexto");
const fechar = document.querySelector(".fechar");

document.querySelectorAll(".promo-col").forEach(card => {
  card.onclick = () => {
    modal.style.display = "block";
    titulo.innerText = card.dataset.title;
    texto.innerHTML = card.dataset.content;
  };
});

fechar.onclick = () => modal.style.display = "none";

window.onclick = (e) => {
  if (e.target == modal) modal.style.display = "none";
};
</script>
</body>
</html>