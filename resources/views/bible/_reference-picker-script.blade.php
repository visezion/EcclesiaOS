<script>
    document.addEventListener('DOMContentLoaded', () => {
        const picker = document.getElementById(@js($pickerId));
        if (!picker || picker.dataset.referencePickerReady === 'true') return;

        picker.dataset.referencePickerReady = 'true';
        const book = picker.querySelector('[data-bible-book]');
        const chapter = picker.querySelector('[data-bible-chapter]');
        const verse = picker.querySelector('[data-bible-verse]');
        const endpoint = picker.dataset.optionsUrl;
        let requestNumber = 0;

        const replaceOptions = (select, values, selected, label) => {
            select.replaceChildren();
            if (!values.length) {
                select.add(new Option(`No ${label.toLowerCase()} available`, ''));
                select.disabled = true;
                return;
            }

            values.forEach((value) => select.add(new Option(`${label} ${value}`, String(value))));
            select.value = values.map(String).includes(String(selected)) ? String(selected) : String(values[0]);
            select.disabled = false;
        };

        const resetOptions = () => {
            replaceOptions(chapter, [], '', 'Chapter');
            replaceOptions(verse, [], '', 'Verse');
        };

        const loadOptions = async (requestedChapter = null, requestedVerse = null) => {
            if (!book.value) {
                resetOptions();
                return;
            }

            const currentRequest = ++requestNumber;
            chapter.disabled = true;
            verse.disabled = true;
            picker.setAttribute('aria-busy', 'true');

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('book', book.value);
                if (requestedChapter) url.searchParams.set('chapter', requestedChapter);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error(`Unable to load Bible reference options (${response.status}).`);
                const data = await response.json();
                if (currentRequest !== requestNumber) return;
                replaceOptions(chapter, data.chapters ?? [], data.chapter, 'Chapter');
                replaceOptions(verse, data.verses ?? [], requestedVerse, 'Verse');
            } catch (error) {
                if (currentRequest === requestNumber) {
                    resetOptions();
                    console.error(error);
                }
            } finally {
                if (currentRequest === requestNumber) picker.removeAttribute('aria-busy');
            }
        };

        book.addEventListener('change', () => loadOptions());
        chapter.addEventListener('change', () => loadOptions(chapter.value));
    });
</script>
