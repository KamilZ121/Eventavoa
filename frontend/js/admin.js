const ADMIN_URL = "/eventavoa/backend/logic/adminHandler.php";
const PRODUCT_URL = "/eventavoa/backend/logic/productHandler.php";
let adminProducts = [];

$(function () {
    $("#header").load("/eventavoa/frontend/sites/header.html");
    $("#footer").load("/eventavoa/frontend/sites/footer.html");
    loadAdminData();
    $("#productForm").on("submit", saveProduct);
    $("#voucherForm").on("submit", createVoucher);
    $("#resetProduct").on("click", resetProductForm);
    $(document).on("click", ".edit-product", editProduct)
        .on("click", ".toggle-product", toggleProduct)
        .on("click", ".toggle-customer", toggleCustomer)
        .on("click", ".customer-orders", loadCustomerOrders)
        .on("click", ".remove-order-item", removeOrderItem);
});

function request(data, method = "GET") {
    return $.ajax({url: ADMIN_URL, method, dataType: "json", data}).fail(function (xhr) {
        if (xhr.status === 401 || xhr.status === 403) window.location.href = "/eventavoa/frontend/sites/login.html";
        showAdminMessage(xhr.responseJSON?.message || "Anfrage fehlgeschlagen.", false);
    });
}

function loadAdminData() {
    $.ajax({url: PRODUCT_URL, dataType: "json", data: {action: "getCategories"}}).done(function (r) {
        $("#productCategory").html(r.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`));
    });
    loadProducts(); loadCustomers(); loadVouchers();
}

function loadProducts() { request({action: "getProducts"}).done(function (r) {
    adminProducts = r.products;
    $("#adminProducts").html(`<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Preis</th><th>Bewertung</th><th>Status</th><th></th></tr></thead><tbody>${r.products.map(p => `<tr><td>${escapeHtml(p.name)}</td><td>${Number(p.price).toFixed(2)} €</td><td>${p.rating}/5</td><td>${Number(p.is_active) ? "aktiv" : "inaktiv"}</td><td><button class="btn btn-sm btn-outline-primary edit-product" data-id="${p.id}">Bearbeiten</button> <button class="btn btn-sm btn-outline-secondary toggle-product" data-id="${p.id}" data-active="${Number(p.is_active) ? 0 : 1}">${Number(p.is_active) ? "Deaktivieren" : "Aktivieren"}</button></td></tr>`).join("")}</tbody></table></div>`);
}); }

function saveProduct(event) { event.preventDefault(); $.ajax({url: ADMIN_URL, method: "POST", dataType: "json", data: new FormData(this), processData: false, contentType: false}).done(function () { resetProductForm(); loadProducts(); showAdminMessage("Produkt gespeichert.", true); }).fail(xhr => showAdminMessage(xhr.responseJSON?.message || "Speichern fehlgeschlagen.", false)); }
function editProduct() { const p = adminProducts.find(x => String(x.id) === String($(this).data("id"))); if (!p) return; $("#productId").val(p.id); $("#productCategory").val(p.category_id); $("#productName").val(p.name); $("#productPrice").val(p.price); $("#productRating").val(p.rating); $("#productDescription").val(p.description); window.scrollTo({top: 0, behavior: "smooth"}); }
function resetProductForm() { $("#productForm")[0].reset(); $("#productId").val(""); }
function toggleProduct() { request({action: "setProductActive", id: $(this).data("id"), active: $(this).data("active")}, "POST").done(loadProducts); }

function loadCustomers() { request({action: "getCustomers"}).done(function (r) { $("#adminCustomers").html(`<div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>E-Mail</th><th>Bestellungen</th><th></th></tr></thead><tbody>${r.customers.map(c => `<tr><td>${escapeHtml(c.vorname + " " + c.nachname)}</td><td>${escapeHtml(c.email)}</td><td>${c.orders_count}</td><td><button class="btn btn-sm btn-outline-primary customer-orders" data-id="${c.id}">Details</button> <button class="btn btn-sm btn-outline-secondary toggle-customer" data-id="${c.id}" data-active="${Number(c.aktiv) ? 0 : 1}">${Number(c.aktiv) ? "Deaktivieren" : "Aktivieren"}</button></td></tr>`).join("")}</tbody></table></div>`); }); }
function toggleCustomer() { request({action: "setCustomerActive", id: $(this).data("id"), active: $(this).data("active")}, "POST").done(loadCustomers); }
function loadCustomerOrders() { const id = $(this).data("id"); request({action: "getCustomerOrders", customer_id: id}).done(function (r) { $("#customerOrders").html(`<h3>Bestellpositionen</h3>${r.items.length ? r.items.map(i => `<div class="card card-body mb-2"><span>Bestellung ${i.order_id}: ${escapeHtml(i.produktname)} (${i.menge} × ${Number(i.einzelpreis).toFixed(2)} €)</span><button class="btn btn-sm btn-outline-danger mt-2 remove-order-item" data-id="${i.item_id}" data-customer="${id}">Position entfernen</button></div>`).join("") : '<p>Keine Bestellungen.</p>'}`); }); }
function removeOrderItem() { const customer = $(this).data("customer"); request({action: "removeOrderItem", item_id: $(this).data("id")}, "POST").done(function () { $(`.customer-orders[data-id="${customer}"]`).trigger("click"); }); }

function loadVouchers() { request({action: "getVouchers"}).done(function (r) { $("#adminVouchers").html(`<table class="table"><thead><tr><th>Code</th><th>Wert</th><th>Restwert</th><th>Ablauf</th><th>Status</th></tr></thead><tbody>${r.vouchers.map(v => `<tr><td><code>${v.code}</code></td><td>${Number(v.initial_value).toFixed(2)} €</td><td>${Number(v.remaining_value).toFixed(2)} €</td><td>${v.expires_at}</td><td>${v.status}</td></tr>`).join("")}</tbody></table>`); }); }
function createVoucher(event) { event.preventDefault(); const data = Object.fromEntries(new FormData(this)); data.action = "createVoucher"; request(data, "POST").done(function (r) { $("#voucherForm")[0].reset(); loadVouchers(); showAdminMessage("Gutschein " + r.code + " wurde erstellt.", true); }); }
function showAdminMessage(text, success) { $("#adminMessage").removeClass("d-none alert-success alert-danger").addClass(success ? "alert-success" : "alert-danger").text(text); }
function escapeHtml(value) { return $("<div>").text(value == null ? "" : String(value)).html(); }

