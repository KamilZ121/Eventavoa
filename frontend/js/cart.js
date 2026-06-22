const CART_URL = "/eventavoa/backend/logic/cartHandler.php";
const ORDER_URL = "/eventavoa/backend/logic/orderHandler.php";
const PAYMENT_URL = "/eventavoa/backend/logic/paymentHandler.php";

$(document).ready(function () {
    loadCart();

    // Bestellen
    $("#cartContent").on("click", "#orderBtn", function () {
        placeOrder();
    });

    // Menge erhöhen
    $("#cartContent").on("click", ".increaseBtn", function () {
        const productId = $(this).data("product-id");
        const qty = $(this).data("qty");
        updateQty(productId, qty + 1);
    });

    // Menge verringern 
    $("#cartContent").on("click", ".decreaseBtn", function () {
        const productId = $(this).data("product-id");
        const qty = $(this).data("qty");
        updateQty(productId, qty - 1);
    });

    // Produkt entfernen
    $("#cartContent").on("click", ".removeBtn", function () {
        const productId = $(this).data("product-id");
        removeFromCart(productId);
    });
});

// Warenkorb Inhalt vom Server holen und anzeigen
function loadCart() {
    $.ajax({
        url: CART_URL,
        method: "GET",
        dataType: "json",
        data: { action: "getCart" },
        success: function (cart) {
            renderCart(cart);
            $("#cartCount").text(cart.count);
        }
    });
}

// Menge eines Produkts setzen
function updateQty(productId, qty) {
    $.ajax({
        url: CART_URL,
        method: "POST",
        dataType: "json",
        data: { action: "updateCart", product_id: productId, qty: qty },
        success: function (cart) {
            renderCart(cart);
            $("#cartCount").text(cart.count);
        }
    });
}

// Produkt aus dem Warenkorb entfernen
function removeFromCart(productId) {
    $.ajax({
        url: CART_URL,
        method: "POST",
        dataType: "json",
        data: { action: "removeFromCart", product_id: productId },
        success: function (cart) {
            renderCart(cart);
            $("#cartCount").text(cart.count);
        }
    });
}

// Bestellen
function placeOrder() {
    const paymentSelect = document.getElementById("paymentSelect");
    const data = { action: "placeOrder" };
    if (paymentSelect) {
        data.payment_method_id = paymentSelect.value;
    }
    data.voucher_code = $("#voucherCode").val() || "";

    $.ajax({
        url: ORDER_URL,
        method: "POST",
        dataType: "json",
        data: data,
        success: function (res) {
            // wenn nicht eingeloggt dann redirect
            if (res.needsLogin) {
                window.location.href = "/eventavoa/frontend/sites/login.html?redirect=" +
                    encodeURIComponent("/eventavoa/frontend/sites/cart.html");
                return;
            }
            if (res.success) {
                $("#cartContent").html(`
                    <div class="alert alert-success">
                        Vielen Dank! Ihre Bestellung Nr. ${res.orderId} über
                        ${res.gesamt.toFixed(2)} € wurde aufgenommen.
                    </div>`);
                $("#cartCount").text(0);
            } else {
                alert(res.message);
            }
        },
        error: function (xhr) {
            const res = xhr.responseJSON || {};
            if (res.needsLogin) {
                window.location.href = "/eventavoa/frontend/sites/login.html?redirect=" +
                    encodeURIComponent("/eventavoa/frontend/sites/cart.html");
                return;
            }
            alert(res.message || "Bestellung konnte nicht abgeschlossen werden.");
        }
    });
}

// Warenkorb als Liste rendern
function renderCart(cart) {
    if (cart.items.length === 0) {
        $("#cartContent").html(`<div class="alert alert-warning">Ihr Warenkorb ist leer.</div>`);
        return;
    }

    let rows = "";
    cart.items.forEach(item => {
        rows += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${item.name}</h6>
                    <small class="text-muted">${item.price.toFixed(2)} € pro Stück</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary decreaseBtn" data-product-id="${item.product_id}" data-qty="${item.qty}">−</button>
                    <span>${item.qty}</span>
                    <button class="btn btn-sm btn-outline-secondary increaseBtn" data-product-id="${item.product_id}" data-qty="${item.qty}">+</button>
                    <span class="fw-bold ms-3" style="min-width: 90px; text-align: right;">${item.line_total.toFixed(2)} €</span>
                    <button class="btn btn-sm btn-outline-danger removeBtn ms-2" data-product-id="${item.product_id}">Entfernen</button>
                </div>
            </div>
        `;
    });

    $("#cartContent").html(`
        <div class="list-group mb-4">${rows}</div>
        <div id="paymentChoice" class="mb-3"></div>
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Gesamt: ${cart.total.toFixed(2)} €</h4>
            <button class="btn btn-primary btn-lg" id="orderBtn">Bestellen</button>
        </div>
    `);
    loadPaymentChoices();
}

// Zahlungsmöglichkeiten für die Auswahl beim Bestellen laden
function loadPaymentChoices() {
    $.ajax({
        url: PAYMENT_URL,
        method: "GET",
        dataType: "json",
        data: {
            action: "getPaymentMethods"
        },
        success: function (res) {
            if (!res.success) {
                return;
            }

            const el = document.getElementById("paymentChoice");

            if (!el) {
                return;
            }

            if (res.methods.length === 0) {
                el.innerHTML = `
                    <div class="alert alert-warning mb-0">
                        Bitte zuerst eine <a href="/eventavoa/frontend/sites/konto.html">Zahlungsmöglichkeit hinterlegen</a>,
                        um bestellen zu können.
                    </div>`;
                return;
            }

            let options = "";

            res.methods.forEach(m => {
                options += `<option value="${m.id}">${m.typ} – ${m.nummer_maskiert}</option>`;
            });

            el.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Zahlungsart</label>
                    <select id="paymentSelect" class="form-select"><option value="">Keine</option>${options}</select></div>
                    <div class="col-md-6"><label class="form-label">Gutscheincode (optional)</label>
                    <input id="voucherCode" class="form-control text-uppercase" maxlength="5"></div>
                </div>`;
        }
    });
}

// Counter im Header
function updateCartBadge() {
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
