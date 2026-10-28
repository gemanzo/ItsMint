<?php

test('the frontend card vocabulary is generated from the current enums', function () {
    // Fails when CardCondition or CardLanguage changed without regenerating
    // resources/js/constants/generated/cardVocabulary.ts
    $this->artisan('itsmint:export-card-vocabulary --check')->assertSuccessful();
});
