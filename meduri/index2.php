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

        .insta-item {
  background: #111;
  padding: 10px;
  border-radius: 15px;
  overflow: hidden;
}

.instagram-media {
  width: 100% !important;
  min-width: 100% !important;
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

    <!-- Equipe - Swiper com 9 cards -->
    <section id="equipe" class="py-5">
        <div class="container" data-aos="fade-up">
            <h2 class="text-center mb-5">Nossa Equipe</h2>
            <div class="swiper equipe-swiper">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <div class="team-card">
                            <img src="img/meduri.png" class="team-img" alt="Meduri tatuador" loading="lazy">
                            <div class="team-overlay">
                                <h4>Meduri</h4>
                                <p>Gustavo Meduri, mais conhecido como Meduri, é tatuador especializado nos estilos Realismo e Black and Gray, reconhecido pela extrema atenção aos detalhes, precisão técnica e acabamento refinado em cada peça. Seu trabalho se destaca pela profundidade, contraste e fidelidade às referências, buscando sempre um resultado marcante e duradouro.</p>

<p>Além da qualidade artística, Meduri preza por um atendimento cuidadoso e personalizado, entendendo a ideia de cada cliente e acompanhando todo o processo com atenção, desde a criação até a finalização da tatuagem. Seu compromisso é proporcionar não apenas um excelente resultado estético, mas também uma experiência segura, confortável e profissional.</p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="team-card">
                            <img src="img/vinicius.png" class="team-img" alt="Lucas tatuador" loading="lazy">
                            <div class="team-overlay">
                                <h4>Vinícios</h4>
                                <p>Vinícios, mais conhecido como Vini, é tatuador de estilo versátil, atuando com diferentes propostas e técnicas, o que permite desenvolver projetos únicos e personalizados para cada cliente. Seu trabalho é marcado pela adaptabilidade, criatividade e atenção aos detalhes, sempre buscando harmonia, boa aplicação e um resultado final consistente, independentemente do estilo escolhido.</p>

<p>No atendimento, Vini se destaca pela proximidade e cuidado em entender a ideia de cada pessoa, oferecendo orientação durante todo o processo criativo e de execução. Seu foco vai além da tatuagem em si, priorizando uma experiência leve, segura e bem acompanhada, garantindo que cada cliente se sinta confiante e satisfeito do início ao fim.</p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="team-card">
                            <img src="img/veronica.png" class="team-img" alt="Ana tatuadora" loading="lazy">
                            <div class="team-overlay">
                                <h4>Verônica</h4>
                                <p>Veronica é tatuadora especializada nos estilos geek e artes conceituais, destacando-se pela criatividade, originalidade e riqueza de detalhes em cada projeto. Seu trabalho traz composições únicas, com forte influência de universos fictícios e ideias autorais, sempre buscando equilíbrio, identidade visual e acabamento de alto nível.</p>

<p>No contato com os clientes, Veronica valoriza uma abordagem atenciosa e individualizada, dedicando tempo para compreender cada proposta e transformar conceitos em tatuagens exclusivas. Seu compromisso é oferecer não apenas um resultado artístico marcante, mas também uma experiência acolhedora, segura e pensada em cada etapa do processo.</p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </section>

    

<section id="galeria" class="py-5">
  <div class="container">
    
    <div class="text-center mb-5">
      <h2>Galeria</h2>
      <p>Alguns dos nossos trabalhos mais recentes</p>
    </div>

    <div class="row g-4" id="instaGallery"></div>

  </div>
</section>





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
const posts = [
  "https://www.instagram.com/p/DWEfEtstY5J/",
  "https://www.instagram.com/p/DWBi7icO2an/",
  "https://www.instagram.com/p/DUgrU4KEQl2/",
  "https://www.instagram.com/p/DPMIi3xDZJE/"
];

const container = document.getElementById("instaGallery");

posts.forEach(link => {
  const col = document.createElement("div");
  col.className = "col-12 col-md-6 col-lg-4";

  col.innerHTML = `
    <div class="insta-item">
      <blockquote class="instagram-media" data-instgrm-permalink="${link}" data-instgrm-version="14"></blockquote>
    </div>
  `;

  container.appendChild(col);
});
</script>

<script async src="//www.instagram.com/embed.js"></script>
</body>
</html>