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
        url: "/eventavoa/backend/logic/requestHandler.php",
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
        url: "/eventavoa/backend/logic/requestHandler.php",
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
                    <div class="card h-100">
                        <div class="bg-light p-5 text-center">
                            <h1 class="text-primary">${product.name.charAt(0).toUpperCase()}</h1>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">${product.name}</h5>
                            <p class="card-text text-muted">${product.description || 'Professionelle Ausruestung'}</p>
                            <p class="fs-5 fw-bold text-primary mt-auto mb-3">${Number(product.price).toFixed(2)} €</p>
                            <button class="btn btn-primary w-100">In den Warenkorb</button>
                        </div>
                    </div>
                </div>
            `;
        });
    }

    $("#productList").html(html);
}