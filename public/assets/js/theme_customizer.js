/**
 * CasaPRO — Personalizador de Tema Alina Adaptado
 *
 * Basado en admin-dashboard/alina/assets/js/theme_customizer.js.
 * Adaptaciones oficiales CasaPRO:
 * - Traducido 100% al español.
 * - Iconografía Font Awesome (fa-solid fa-gear).
 * - Sin botones ni enlaces comerciales externos.
 * - Omitidas variantes de barra lateral y tamaños de fuente.
 * - Conservadas opciones: Colores del tema (gradientes 1-6), Diseños (ltr, rtl, box) y Restablecer.
 */

document.addEventListener("DOMContentLoaded", function () {
    const customizerBox = document.getElementById("theme-customizer-box");
    if (!customizerBox) {
        return;
    }

    const htmlPersonalizador = `
<div class="theme-customizer-container" id="theme-customizer">
    <div class="customizer-box">
         <span class="w-35 h-35 d-flex-center b-r-12 cursor-pointer customizer-settings-btn"
               data-bs-toggle="offcanvas"
               data-bs-target="#offcanvasSettings"
               title="Personalizar Tema">
              <i class="fa-solid fa-gear f-s-22"></i>
         </span>
    </div>
</div>

<div class="offcanvas offcanvas-end canvas-settings" tabindex="-1" id="offcanvasSettings" aria-labelledby="offcanvasSettingsLabel">
    <div class="offcanvas-header bg-dark">
        <h5 class="offcanvas-title text-white" id="offcanvasSettingsLabel">Personalizador de Tema</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body">
       <div>
           <span class="title-badge-text">Colores del tema:</span>
           <ul class="theme-color-list d-flex align-items-center gap-3 m-3">
               <li class="theme-gradient-1 cursor-pointer" title="Gradiente 1"></li>
               <li class="theme-gradient-2 cursor-pointer" title="Gradiente 2"></li>
               <li class="theme-gradient-3 cursor-pointer" title="Gradiente 3"></li>
               <li class="theme-gradient-4 cursor-pointer" title="Gradiente 4"></li>
               <li class="theme-gradient-5 cursor-pointer" title="Gradiente 5"></li>
               <li class="theme-gradient-6 cursor-pointer" title="Gradiente 6"></li>
           </ul>
       </div>
        <div class="mt-4">
            <span class="title-badge-text">Diseños del tema:</span>
            <ul class="d-flex mt-3 gap-3 theme-layout-list">

                <li class="ltr-layout cursor-pointer">
                  <ul class="layout">
                    <li class="layout-badge"><span class="badge bg-gradient-secondary cursor-pointer">ltr</span></li>
                    <li class="sidebar"></li>
                    <li class="content">
                      <ul>
                        <li class="header"></li>
                        <li class="body"></li>
                      </ul>
                    </li>
                  </ul>
                </li>

                <li class="rtl-layout cursor-pointer">
                 <ul class="layout">
                    <li class="layout-badge"><span class="badge bg-gradient-secondary cursor-pointer">rtl</span></li>
                    <li class="sidebar"></li>
                    <li class="content">
                      <ul>
                        <li class="header"></li>
                        <li class="body"></li>
                      </ul>
                    </li>
                  </ul>
                </li>

                <li class="box-layout cursor-pointer">
                 <ul class="layout">
                    <li class="layout-badge"><span class="badge bg-gradient-secondary cursor-pointer">box</span></li>
                    <li class="sidebar"></li>
                    <li class="content">
                      <ul>
                        <li class="header"></li>
                        <li class="body"></li>
                      </ul>
                    </li>
                  </ul>
                </li>
            </ul>
        </div>
    </div>
    <div class="offcanvas-footer p-3">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger w-100" id="btn-reset-customizer" onclick="resetCustomizer()">Restablecer</button>
        </div>
    </div>
</div>
    `;

    customizerBox.innerHTML = htmlPersonalizador;

    // --- Estado activo del botón al abrir/cerrar offcanvas ---
    const customizerContainer = document.querySelector('.theme-customizer-container');
    const settingsBtn = document.querySelector('.customizer-settings-btn');
    const offcanvasEl = document.getElementById('offcanvasSettings');

    if (settingsBtn && offcanvasEl) {
        offcanvasEl.addEventListener('shown.bs.offcanvas', () => {
            settingsBtn.classList.add('active');
            if (customizerContainer) {
                customizerContainer.classList.add('canvas-active');
            }
        });

        offcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
            settingsBtn.classList.remove('active');
            if (customizerContainer) {
                customizerContainer.classList.remove('canvas-active');
            }
        });
    }

    // --- Lógica de Colores del Tema ---
    const themeClasses = [
        'theme-gradient-1',
        'theme-gradient-2',
        'theme-gradient-3',
        'theme-gradient-4',
        'theme-gradient-5',
        'theme-gradient-6'
    ];

    const savedTheme = localStorage.getItem('theme_color') || 'theme-gradient-1';
    document.body.classList.remove(...themeClasses);
    document.body.classList.add(savedTheme);

    document.querySelectorAll('.theme-color-list li').forEach(li => {
        li.classList.toggle('active', li.classList.contains(savedTheme));
    });

    document.addEventListener('click', function (e) {
        const li = e.target.closest('.theme-color-list li');
        if (!li) return;

        document.body.classList.remove(...themeClasses);
        themeClasses.forEach(cls => {
            if (li.classList.contains(cls)) {
                document.body.classList.add(cls);
                localStorage.setItem('theme_color', cls);
            }
        });

        li.parentElement.querySelectorAll('li').forEach(el => el.classList.remove('active'));
        li.classList.add('active');
    });

    // --- Lógica de Diseños del Tema (Layouts) ---
    const layoutItems = document.querySelectorAll(".theme-layout-list li");
    let savedLayout = localStorage.getItem("theme_layout") || "ltr";

    function applyLayout(layout) {
        document.body.classList.remove("layout-ltr", "layout-rtl", "box-layout");
        document.body.removeAttribute("dir");

        if (layout === "ltr") {
            document.body.classList.add("layout-ltr");
        } else if (layout === "rtl") {
            document.body.classList.add("layout-rtl");
            document.body.setAttribute("dir", "rtl");
        } else if (layout === "box") {
            document.body.classList.add("box-layout");
        }
    }

    function highlightActiveLayout(activeLayout) {
        layoutItems.forEach(li => {
            li.classList.remove("active");
            if (li.textContent.trim().toLowerCase() === activeLayout) {
                li.classList.add("active");
            }
        });
    }

    applyLayout(savedLayout);
    highlightActiveLayout(savedLayout);

    layoutItems.forEach(item => {
        item.addEventListener("click", () => {
            const layout = item.textContent.trim().toLowerCase();
            applyLayout(layout);
            highlightActiveLayout(layout);
            localStorage.setItem("theme_layout", layout);
        });
    });
});

/**
 * Restablece las configuraciones del personalizador a sus valores predeterminados.
 */
function resetCustomizer() {
    const defaultTheme = 'theme-gradient-1';
    const defaultLayout = 'ltr';

    localStorage.removeItem('theme_color');
    localStorage.removeItem('theme_layout');

    const themeClasses = [
        'theme-gradient-1',
        'theme-gradient-2',
        'theme-gradient-3',
        'theme-gradient-4',
        'theme-gradient-5',
        'theme-gradient-6'
    ];
    document.body.classList.remove(...themeClasses);
    document.body.classList.add(defaultTheme);

    document.querySelectorAll('.theme-color-list li').forEach(li => {
        li.classList.toggle('active', li.classList.contains(defaultTheme));
    });

    document.body.classList.remove("layout-ltr", "layout-rtl", "box-layout");
    document.body.removeAttribute("dir");
    document.body.classList.add("layout-ltr");

    document.querySelectorAll(".theme-layout-list li").forEach(li => {
        li.classList.toggle('active', li.textContent.trim().toLowerCase() === defaultLayout);
    });
}
