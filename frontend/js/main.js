$(document).ready(function () {
    loadCategories();
    loadProducts();

    $("#categorySelect").on("change", function () {
        loadProducts();
    });

    $("#searchInput").on("input", function () {
        loadProducts();
    });
});

function loadCategories() {
    $.ajax({
        url: "../backend/logic/requestHandler.php",
        method: "GET",
        dataType: "json",
        data: {
            action: "getCategories"
        },
        success: function (categories) {
            let options = '<option value="">Alle Kategorien</option>';

            categories.forEach(category => {
                options += `<option value="${category.id}">${category.name}</option>`;
            });

            $("#categorySelect").html(options);
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
        url: "../backend/logic/requestHandler.php",
        method: "GET",
        dataType: "json",
        data: {
            action: "getProducts",
            category_id: categoryId,
            search: search
        },
        success: function (products) {
            renderProducts(products);
            $("#resultCount").text(products.length + " Produkte");
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
            let image = product.image_path ? product.image_path : "img/products/placeholder.jpg";

            html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card product-card h-100 shadow-sm">
                        <img src="${image}" class="card-img-top" alt="${product.name}">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${product.name}</h5>
                            <p class="card-text">${product.description}</p>
                            <p>Bewertung: ${product.rating} / 5</p>
                            <p class="product-price mt-auto">${Number(product.price).toFixed(2)} €</p>
                            <button class="btn btn-primary mt-2" disabled>In den Warenkorb</button>
                        </div>
                    </div>
                </div>
            `;
        });
    }

    $("#productList").html(html);
}