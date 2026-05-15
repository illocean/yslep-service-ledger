# Development

## Testing

```bash
# Run the full test suite
composer run test

# Or directly
php artisan test
```

---

## Code Style

This project follows Laravel Pint (PSR-12) coding standards:

```bash
./vendor/bin/pint --test
```

---

## Frontend Build

```bash
# Production build
npm run build

# Development with HMR
npm run dev
```

The frontend uses:
- **Tailwind CSS v4** -- Utility-first CSS framework
- **Vite** -- Build tool with HMR
- **Alpine.js / vanilla JS** -- Progressive enhancement (minimal JavaScript)

---

## Contributing

Contributions are welcome. Please submit a pull request or open an issue to discuss proposed changes.

---

## License

The YSLEP Service Ledger is open-sourced software licensed under the [MIT license](../LICENSE).
