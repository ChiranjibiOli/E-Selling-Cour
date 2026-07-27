document.querySelector('input[name="email"]')?.focus();

window.courseHubGoogleSignIn = function (response) {
    const credential = typeof response?.credential === 'string' ? response.credential : '';
    const form = document.getElementById('studentGoogleLoginForm');
    const input = form?.querySelector('input[name="google_credential"]');

    if (credential === '' || !(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
        return;
    }

    input.value = credential;
    form.requestSubmit();
};
