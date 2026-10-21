document.addEventListener("DOMContentLoaded", function () {
    // 1. Cambiar entre pestañas de configuración
    const tabCards = document.querySelectorAll(".tab-card");
    
    tabCards.forEach(tab => {
        tab.addEventListener("click", function (e) {
            e.preventDefault();
            
            // Remover clase active de todas las pestañas
            tabCards.forEach(card => card.classList.remove("active"));
            
            // Activar la pestaña clickeada
            this.classList.add("active");

            // Obtener el nombre o tipo de pestaña
            const tabName = this.querySelector("h4").innerText.trim();
            console.log("Pestaña seleccionada: " + tabName);
        });
    });

    // 2. Acción del botón "Realizar respaldo"
    const btnRespaldo = document.querySelector(".btn-outline-brown");
    if (btnRespaldo) {
        btnRespaldo.addEventListener("click", function () {
            if (confirm("¿Deseas generar una copia de seguridad de la base de datos ahora?")) {
                alert("¡Copia de seguridad generada con éxito!");
            }
        });
    }

    // 3. Interruptor de Modo Oscuro
    const switches = document.querySelectorAll(".switch input");
    if (switches[1]) { // Segundo switch (Modo oscuro)
        switches[1].addEventListener("change", function () {
            if (this.checked) {
                document.body.style.backgroundColor = "#1a1a1a";
                document.body.style.color = "#ffffff";
            } else {
                document.body.style.backgroundColor = "var(--bg-main)";
                document.body.style.color = "var(--text-dark)";
            }
        });
    }
});