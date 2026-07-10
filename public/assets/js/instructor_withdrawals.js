document.addEventListener("DOMContentLoaded", function () {
    const paymentMethod = document.getElementById("paymentMethod");

    const bankFields = document.getElementById("bankFields");
    const esewaFields = document.getElementById("esewaFields");
    const khaltiFields = document.getElementById("khaltiFields");

    function updateFields() {
        const selectedMethod = paymentMethod.value;

        bankFields.classList.add("hidden");
        esewaFields.classList.add("hidden");
        khaltiFields.classList.add("hidden");

        if (selectedMethod === "bank") {
            bankFields.classList.remove("hidden");
        }

        if (selectedMethod === "esewa") {
            esewaFields.classList.remove("hidden");
        }

        if (selectedMethod === "khalti") {
            khaltiFields.classList.remove("hidden");
        }
    }

    if (paymentMethod) {
        paymentMethod.addEventListener("change", updateFields);
        updateFields();
    }
});