/**
 * This JavaScript code is designed to enhance password input fields in a web browser by checking
 * entered passwords against the Pwned Passwords API to determine if they have been exposed in data breaches.
 * 
 * Functionality:
 * 1. Automatically finds all input elements with a `data-bad-password-warning-id` attribute upon document load.
 * 2. Initializes these inputs by removing any `badPassword` or `goodPassword` classes, and hides any related warning or notice elements.
 * 3. Attaches an `onchange` event handler to these inputs to perform the following actions when the password changes and is not empty:
 *    - Removes the `badPassword` class if present.
 *    - Hides the warning element referenced by `data-bad-password-warning-id`.
 *    - Computes the SHA1 hash of the password.
 *    - Fetches data from `https://api.pwnedpasswords.com/range/` using the first 5 characters of the SHA1 hash.
 *    - Checks if the fetched data contains a line matching the last 27 characters of the password's SHA1 hash.
 *    - If a match is found (password is pwned):
 *      - Displays the warning element and adds the `badPassword` class to the input.
 *      - If `data-remove-bad-password` is present, clears the input field content and removes the warning as soon as the user starts typing again.
 *    - If no match is found (password is not pwned):
 *      - Ensures the input does not have the `badPassword` class.
 *      - If `data-good-password-notice-id` is set, displays the notice element and adds the `goodPassword` class to the input.
 * 4. Errors encountered during this process are logged to the console, ensuring they do not prevent form submission.
 * 
 * Note: The setup is triggered on document load, allowing for the script to be loaded in the document's head.
 */

document.addEventListener('DOMContentLoaded', function () {
    async function sha1(msg) {
        const buffer = new TextEncoder().encode(msg);
        const digest = await crypto.subtle.digest('SHA-1', buffer);
        const hashArray = Array.from(new Uint8Array(digest));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function checkPassword(input, sha1Hash) {
        const prefix = sha1Hash.substring(0, 5);
        const suffix = sha1Hash.substring(5).toUpperCase();
        try {
            const response = await fetch(`https://api.pwnedpasswords.com/range/${prefix}`);
            const data = await response.text();
            const lines = data.split('\n');
            const found = lines.some(line => line.split(':')[0] === suffix);
            const badPasswordWarningId = input.getAttribute('data-bad-password-warning-id');
            const goodPasswordNoticeId = input.getAttribute('data-good-password-notice-id');
            const removeBadPassword = input.hasAttribute('data-remove-bad-password');
            const badWarningElement = document.getElementById(badPasswordWarningId);
            const goodNoticeElement = goodPasswordNoticeId ? document.getElementById(goodPasswordNoticeId) : null;

            if (found) {
                if (removeBadPassword) {
                    input.value = ''; // Clear input if bad password is detected
                }
                if (badWarningElement) badWarningElement.style.display = '';
                if (goodNoticeElement) goodNoticeElement.style.display = 'none';
                input.classList.add('badPassword');
                input.classList.remove('goodPassword');
            } else {
                if (badWarningElement) badWarningElement.style.display = 'none';
                if (goodNoticeElement) goodNoticeElement.style.display = '';
                input.classList.remove('badPassword');
                input.classList.add('goodPassword');
            }
        } catch (error) {
            console.error(error);
        }
    }

    document.querySelectorAll('input[data-bad-password-warning-id]').forEach(input => {
        const badPasswordWarningId = input.getAttribute('data-bad-password-warning-id');
        const goodPasswordNoticeId = input.getAttribute('data-good-password-notice-id');
        const badWarningElement = document.getElementById(badPasswordWarningId);
        const goodNoticeElement = goodPasswordNoticeId ? document.getElementById(goodPasswordNoticeId) : null;

        if (badWarningElement) badWarningElement.style.display = 'none';
        if (goodNoticeElement) goodNoticeElement.style.display = 'none';

        input.classList.remove('badPassword', 'goodPassword');

        input.addEventListener('change', async () => {
            const password = input.value;
            if (password) {
                const sha1Hash = await sha1(password);
                await checkPassword(input, sha1Hash);
            }
        });

        if (input.hasAttribute('data-remove-bad-password')) {
            input.addEventListener('input', () => {
                if (badWarningElement) badWarningElement.style.display = 'none';
                input.classList.remove('badPassword');
            });
        }
    });
});
