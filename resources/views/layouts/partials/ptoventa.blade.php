<!-- ======= Sección Pública de Productos (Punto de Venta) ======= -->
<section id="ptoventa" class="portfolio sinfondo py-5">
    <div class="container" data-aos="fade-up">

        <!-- Título organizado -->
        <div class="section-title text-center mb-5">
            <h2 class="fw-bold">Punto de Venta</h2>
            <p class="text-muted">Explora nuestros productos actuales y recientes. Disponibles para todos.</p>
        </div>

        <!-- Carrusel de categorías (renglón deslizable con botones) -->
        <div class="swiper categories-swiper mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="swiper-wrapper d-flex align-items-center">
                <!-- Slide "Todos" -->
                <div class="swiper-slide">
                    <button data-filter="*" class="filter-active btn btn-outline-orange mx-1 px-4 py-2 rounded-pill">Todos</button>
                </div>
                @if(isset($categories) && $categories->count() > 0)
                    @foreach($categories as $category)
                        <?php 
                            $slug = strtolower(preg_replace('/[^a-z0-9]/', '', str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ', '&', '/'], ['', 'a', 'e', 'i', 'o', 'u', 'n', '', ''], $category->name)));
                        ?>
                        <div class="swiper-slide">
                            <button data-filter=".filter-{{ $slug }}" class="btn btn-outline-orange mx-1 px-4 py-2 rounded-pill">
                                {{ $category->name }} ({{ $category->elements_count }})
                            </button>
                        </div>
                    @endforeach
                @else
                    <div class="swiper-slide text-muted">No hay categorías disponibles.</div>
                @endif
            </div>
            <!-- Botones de navegación (pasar uno por uno) -->
            <div class="swiper-button-next text-orange"></div>
            <div class="swiper-button-prev text-orange"></div>
        </div>

        <!-- Grid de productos - Responsivo y uniforme -->
        <div class="row portfolio-container g-4" data-aos="fade-up" data-aos-delay="200">
            @if(isset($elements) && $elements->count() > 0)
                @foreach($elements as $element)
                    <?php 
                        $categoryName = $element->category->name ?? 'Sin categoría';
                        $filterClass = 'filter-' . strtolower(preg_replace('/[^a-z0-9]/', '', str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ', '&', '/'], ['', 'a', 'e', 'i', 'o', 'u', 'n', '', ''], $categoryName)));
                        
                        $imagePath = $element->image && file_exists(public_path($element->image)) ? asset($element->image) : asset('modules/sica/images/sinImagen.png');
                        $priceFormatted = function_exists('priceFormat') ? priceFormat($element->price) : '$ ' . number_format($element->price);
                    ?>
                    <div class="col-lg-4 col-md-6 portfolio-item {{ $filterClass }}">
                        <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden">
                            <!-- Imagen con zoom y ribbon precio -->
                            <a href="{{ $imagePath }}" class="portfolio-lightbox preview-link d-block">
                                <div class="position-relative" style="height: 250px; overflow: hidden;">
                                    <img src="{{ $imagePath }}" class="card-img-top w-100 h-100" style="object-fit: cover; transition: transform 0.3s ease;" alt="{{ $element->name }}">
                                    <div class="position-absolute top-0 end-0 m-3">
                                        <span class="badge bg-orange fs-6 px-3 py-2 rounded-pill">{{ $priceFormatted }}</span>
                                    </div>
                                </div>
                            </a>
                            <!-- Info de producto -->
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold mb-1">{{ $element->name }}</h5>
                                <p class="card-text text-muted small flex-grow-1">{{ $element->description ?? 'Categoría: ' . $categoryName }}</p>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <a href="{{ $imagePath }}" data-gallery="portfolioGallery" class="portfolio-lightbox btn btn-sm btn-outline-orange" title="Ampliar">
                                        <i class="bx bx-plus"></i>
                                    </a>
                                    <!-- Botón a módulo protegido -->
                                    <a href="{{ route('ptventa.admin.element.index') }}" class="btn btn-sm btn-orange text-white" title="Gestionar en PTO Venta">
                                        <i class="bx bx-link"></i> Ver en PTO
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12 text-center py-5">
                    <p class="text-muted fs-4">No hay productos disponibles en este momento. ¡Vuelve pronto!</p>
                </div>
            @endif
        </div>

        <!-- Paginación -->
        @if(isset($elements) && method_exists($elements, 'links'))
            <div class="d-flex justify-content-center mt-4">
                {{ $elements->links('pagination::bootstrap-5') }}
            </div>
        @endif

    </div>
</section>
<!-- End Sección Productos -->

<!-- Estilos personalizados (naranja SICEFA #FD7E14) -->
<style>
    /* Colores naranja SICEFA */
    .btn-outline-orange {
        border-color: #FD7E14 !important;
        color: #FD7E14 !important;
    }
    .btn-outline-orange:hover, .btn-outline-orange.filter-active {
        background-color: #FD7E14 !important;
        color: white !important;
    }
    .btn-orange {
        background-color: #FD7E14 !important;
        border-color: #FD7E14 !important;
    }
    .btn-orange:hover {
        background-color: #E06B00 !important; /* Naranja más oscuro hover */
    }
    .bg-orange {
        background-color: #FD7E14 !important;
    }
    .text-orange {
        color: #FD7E14 !important;
    }

    .categories-swiper {
        overflow: hidden;
        padding: 10px 0;
    }
    .swiper-slide {
        width: auto !important;
        text-align: center;
    }
    .swiper-button-next, .swiper-button-prev {
        background: rgba(255,255,255,0.7);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .swiper-button-next:after, .swiper-button-prev:after {
        font-size: 20px;
        color: #FD7E14;
    }
    .portfolio-item .card:hover img {
        transform: scale(1.05);
    }
    /* Hovers naranjas */
    .portfolio-item .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(253, 126, 20, 0.2) !important; /* Sombra naranja */
    }
</style>

<!-- JS para Swiper, Isotope y GLightbox (sin descarga) -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa Swiper para categorías
        const categoriesSwiper = new Swiper('.categories-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: { slidesPerView: 3 },
                768: { slidesPerView: 5 },
                1024: { slidesPerView: 7 },
            }
        });

        // Delegación clicks filtros
        document.querySelector('.categories-swiper').addEventListener('click', function(e) {
            if (e.target.matches('[data-filter]')) {
                const filterValue = e.target.getAttribute('data-filter');
                document.querySelectorAll('[data-filter]').forEach(btn => btn.classList.remove('filter-active'));
                e.target.classList.add('filter-active');
                iso.arrange({ filter: filterValue });
            }
        });

        // Isotope para productos
        var iso = new Isotope('.portfolio-container', {
            itemSelector: '.portfolio-item',
            layoutMode: 'fitRows'
        });

        // GLightbox reforzado: Sin descarga, sin arrastre, sin Excel (modal puro)
        const lightbox = GLightbox({
            selector: '.portfolio-lightbox',
            draggable: false,
            downloadButton: false, // Elimina botón descarga
            touchNavigation: false, // Evita interacciones raras
            openEffect: 'zoom', // Efecto suave
            closeEffect: 'zoom',
            // Override cualquier download forzado
            onOpen: () => console.log('Imagen ampliada - sin descarga'),
            moreLength: 0 // Evita textos extra
        });
    });
</script>