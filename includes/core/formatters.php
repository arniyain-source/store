<?php

function formatPrice(float|int $amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function generateSlug(string $string): string {
    // Slug generation logic
}

function generateUniqueSlug(string $string, string $table, ?int $excludeId = null): string {
    // Unique slug generation logic
}
