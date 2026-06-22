const AUTH_URL = "/eventavoa/backend/logic/authHandler.php";
const ORDER_URL = "/eventavoa/backend/logic/orderHandler.php";
const PAYMENT_URL = "/eventavoa/backend/logic/paymentHandler.php";
let passwordModal;

$(document).ready(function () {
    $("#header").load("/eventavoa/frontend/sites/header.html");
    $("#footer").load("/eventavoa/frontend/sites/footer.html");
    $("#profileForm").submit(openPasswordDialog);
    $("#confirmProfile").click(saveProfile);
    $("#paymentForm").submit(addPayment);
    $("#zahl_typ").change(updatePaymentForm);
    $("#paymentList").on("click", ".delete-payment", deletePayment);
    $("#ordersContent").on("click", ".open-invoice", openInvoice);
    updatePaymentForm();
    loadProfile();
});

function loadProfile() {
    $.ajax({type: "GET", url: AUTH_URL, data: {action: "getProfile"}, dataType: "json"})
        .done(function (response) {
            const user = response.user;
            ["anrede", "vorname", "nachname", "email", "benutzername", "adresse", "plz", "ort"].forEach(function (field) {
                $("#" + field).val(user[field] || "");
            });
            $("#profileCard").removeClass("d-none");
            loadPayments();
            loadOrders();
        })
        .fail(function () { $("#notLoggedIn").removeClass("d-none"); });
}

function openPasswordDialog(event) {
    event.preventDefault();
    $("#modalPasswort, #modalError").val("").text("");
    passwordModal = new bootstrap.Modal("#passwordModal");
    passwordModal.show();
}

function saveProfile() {
    const password = $("#modalPasswort").val();
    if (!password) { $("#modalError").text("Bitte Passwort eingeben."); return; }
    const data = {action: "updateProfile", passwort: password};
    ["anrede", "vorname", "nachname", "email", "benutzername", "adresse", "plz", "ort"].forEach(function (field) {
        data[field] = $("#" + field).val();
    });
    $.ajax({type: "POST", url: AUTH_URL, data: data, dataType: "json"})
        .done(function () { passwordModal.hide(); showMessage("Daten gespeichert.", true); })
        .fail(function (xhr) { $("#modalError").text(errorMessage(xhr)); });
}

function loadPayments() {
    $.ajax({type: "GET", url: PAYMENT_URL, data: {action: "getPaymentMethods"}, dataType: "json"})
        .done(function (response) {
            $("#paymentSection").removeClass("d-none");
            if (!response.methods.length) { $("#paymentList").html('<p class="text-muted">Noch keine Zahlungsmöglichkeit hinterlegt.</p>'); return; }
            let html = '<div class="list-group">';
            response.methods.forEach(function (method) {
                html += `<div class="list-group-item d-flex justify-content-between"><span>${escapeHtml(method.typ)} – ${escapeHtml(method.inhaber)} – ${escapeHtml(method.nummer_maskiert)}</span><button class="btn btn-sm btn-outline-danger delete-payment" data-id="${method.id}">Entfernen</button></div>`;
            });
            $("#paymentList").html(html + "</div>");
        });
}

function updatePaymentForm() {
    const type = $("#zahl_typ").val();
    $(".card-only").toggle(type === "Kreditkarte");
    $("#nummerLabel").text(type === "Kreditkarte" ? "Kartennummer" : type === "Rechnung" ? "Rechnungs-E-Mail" : "PayPal-E-Mail");
}

function addPayment(event) {
    event.preventDefault();
    const data = {action: "addPaymentMethod", typ: $("#zahl_typ").val(), inhaber: $("#zahl_inhaber").val(),
        nummer: $("#zahl_nummer").val(), gueltig_bis: $("#zahl_gueltig").val(), pruefziffer: $("#zahl_pruefziffer").val(), passwort: $("#zahl_passwort").val()};
    $.ajax({type: "POST", url: PAYMENT_URL, data: data, dataType: "json"})
        .done(function () { $("#paymentForm")[0].reset(); updatePaymentForm(); loadPayments(); })
        .fail(function (xhr) { showMessage(errorMessage(xhr), false); });
}

function deletePayment() {
    $.ajax({type: "POST", url: PAYMENT_URL, data: {action: "deletePaymentMethod", id: $(this).data("id")}, dataType: "json"}).done(loadPayments);
}

function loadOrders() {
    $.ajax({type: "GET", url: ORDER_URL, data: {action: "getOrders"}, dataType: "json"}).done(function (response) {
        $("#ordersSection").removeClass("d-none");
        if (!response.orders.length) { $("#ordersContent").html('<p class="text-muted">Noch keine Bestellungen.</p>'); return; }
        let html = "";
        response.orders.forEach(function (order) {
            const items = order.items.map(item => item.menge + "× " + escapeHtml(item.name)).join(", ");
            html += `<div class="card mb-3"><div class="card-body"><h3 class="h6">Bestellung Nr. ${order.id}</h3><p>${formatDate(order.created_at)} · ${order.gesamt.toFixed(2)} €</p><p>${items}</p><button class="btn btn-sm btn-outline-primary open-invoice" data-id="${order.id}">Rechnung anzeigen</button></div></div>`;
        });
        $("#ordersContent").html(html);
    });
}

function openInvoice() { window.open("/eventavoa/frontend/sites/rechnung.html?order=" + $(this).data("id"), "_blank"); }
function formatDate(value) { return value.substring(0, 10).split("-").reverse().join(". "); }
function escapeHtml(value) { return $("<div>").text(value == null ? "" : String(value)).html(); }
function errorMessage(xhr) { return xhr.responseJSON?.message || "Die Anfrage ist fehlgeschlagen."; }
function showMessage(text, success) { $("#kontoMessage").removeClass("d-none alert-success alert-danger").addClass(success ? "alert-success" : "alert-danger").text(text); }
