const CART_URL = "/eventavoa/backend/logic/cartHandler.php";

$(document).ready(function () {
    loadCategories();

    $("#categorySelect").on("change", function () {
        loadProducts();
    });

    $("#searchInput").on("input", function () {
        loadProducts();
    });

    // Klick auf "In den Warenkorb"
    $("#productList").on("click", ".addToCartBtn", function () {
        const productId = $(this).data("product-id");
        addToCart(productId);
    });

    // Produkt wird gezogen
    $("#productList").on("dragstart", ".card", function (e) {
        e.originalEvent.dataTransfer.setData("text/plain", $(this).data("product-id"));
    });
});

// Produkt in den Warenkorb legen (AJAX)
function addToCart(productId) {
    $.ajax({
        url: CART_URL,
        method: "POST",
        dataType: "json",
        data: {
            action: "addToCart",
            product_id: productId,
            qty: 1
        },
        success: function (response) {
            updateCartCount(response.count);
            showCartToast();
        }
    });
}

// visuelle Bestätigung
function showCartToast() {
    let toast = $("#cartToast");
    if (toast.length === 0) {
        toast = $('<div id="cartToast" class="position-fixed bottom-0 end-0 m-4 alert alert-success shadow" style="display: none; z-index: 1080;">Produkt in den Warenkorb gelegt</div>');
        $("body").append(toast);
    }
    toast.stop(true, true).fadeIn(150).delay(1200).fadeOut(400);
}

// Drag n drop warenkorb
function bindCartDropZone() {
    const cartLink = $("#cartLink");

    // dragover prevent
    cartLink.on("dragover", function (e) {
        e.preventDefault();
    });

    // product-id auslesen 
    cartLink.on("drop", function (e) {
        e.preventDefault();
        const productId = e.originalEvent.dataTransfer.getData("text/plain");
        if (productId) {
            addToCart(productId);
        }
    });
}

// Anzahl im Warenkorb setzen. Falls kein Wert wird die Anzahl vom Server geholt
function updateCartCount(count) {
    if (typeof count !== "undefined") {
        $("#cartCount").text(count);
        return;
    }

    $.ajax({
        url: CART_URL,
        method: "GET",
        dataType: "json",
        data: { action: "getCartCount" },
        success: function (response) {
            $("#cartCount").text(response.count);
        }
    });
}

function loadCategories() {
    $.ajax({
        url: "/eventavoa/backend/logic/productHandler.php",
        method: "GET",
        dataType: "json",
        data: {
            action: "getCategories"
        },
        success: function (response) {
            let options = '<option value="">Alle Kategorien</option>';

            response.categories.forEach(category => {
                options += `<option value="${category.id}">${category.name}</option>`;
            });

            $("#categorySelect").html(options);
            if (response.categories.length) {
                $("#categorySelect").val(response.categories[0].id);
            }
            loadProducts();
        },
        error: function () {
            alert("Kategorien konnten nicht geladen werden.");
        }
    });
}

function loadProducts() {
    const categoryId = $("#categorySelect").val();
    const search = $("#searchInput").val();

    $.ajax({
        url: "/eventavoa/backend/logic/productHandler.php",
        method: "GET",
        dataType: "json",
        data: {
            action: "getProducts",
            category_id: categoryId,
            search: search
        },
        success: function (response) {
            const products = response.products;
            renderProducts(products);
            // "Produkt" falls es ein einzelnes ist
            const label = products.length === 1 ? " Produkt" : " Produkte";
            $("#resultCount").text(products.length + label);
        },
        error: function () {
            $("#productList").html(`
                <div class="col-12">
                    <div class="alert alert-danger">Produkte konnten nicht geladen werden.</div>
                </div>
            `);
            $("#resultCount").text("0 Produkte");
        }
    });
}

function renderProducts(products) {
    let html = "";

    if (products.length === 0) {
        html = `
            <div class="col-12">
                <div class="alert alert-warning">Keine Produkte gefunden.</div>
            </div>
        `;
    } else {
        products.forEach(product => {
            // Bilder laden
            const media = product.image_path
                ? `<div class="bg-light text-center" style="height: 200px;">
                       <img src="/eventavoa/frontend/${encodeURI(product.image_path)}" alt="${safeText(product.name)}" style="height: 100%; object-fit: contain;">
                   </div>`
                : `<div class="bg-light p-5 text-center">
                       <h1 class="text-primary">${safeText(product.name.charAt(0).toUpperCase())}</h1>
                   </div>`;

            // Drag n drop in Warenkorb
            html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card product-card h-100" draggable="true" data-product-id="${product.id}">
                        ${media}
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${safeText(product.name)}</h5>
                            <p class="card-text text-muted">${safeText(product.description || 'Professionelle Ausrüstung')}</p>
                            <div class="text-warning mb-2" aria-label="Bewertung ${product.rating} von 5">${renderRating(product.rating)}</div>
                            <p class="fs-5 fw-bold text-primary mt-auto mb-3">${Number(product.price).toFixed(2)} €</p>
                            <button class="btn btn-primary w-100 addToCartBtn" data-product-id="${product.id}">In den Warenkorb</button>
                        </div>
                    </div>
                </div>
            `;
        });
    }

    $("#productList").html(html);
}

function renderRating(value) {
    const rating = Math.max(0, Math.min(5, Math.round(Number(value))));
    return "★".repeat(rating) + "☆".repeat(5 - rating);
}

function safeText(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
}
