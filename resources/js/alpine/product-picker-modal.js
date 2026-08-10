export function registerProductPickerModal(Alpine) {
    Alpine.data('productPickerModal', (config) => ({
        open: config.autoOpen ?? false,
        productOpen: false,
        activeCategory: config.activeCategory ?? null,
        products: config.products ?? [],
        selectedProduct: null,
        selectedVariantId: null,
        qty: 1,
        notes: '',
        cartCount: 0,
        cartTotal: 0,
        adding: false,
        submitting: false,
        comanda: config.comanda ?? null,
        addUrl: config.addUrl,
        storeUrl: config.storeUrl,
        summaryUrl: config.summaryUrl ?? null,
        returnUrl: config.returnUrl ?? null,
        csrf: config.csrf,
        pickerId: config.pickerId ?? 'default',

        init() {
            this.refreshCart();
        },

        openPicker() {
            this.open = true;
            this.refreshCart();
        },

        openProduct(id) {
            this.selectedProduct = this.products.find((p) => p.id === id) || null;
            this.selectedVariantId = this.selectedProduct?.has_variants
                ? this.selectedProduct.variants[0]?.id ?? null
                : null;
            this.qty = 1;
            this.notes = '';
            this.productOpen = true;
        },

        selectedPrice() {
            if (!this.selectedProduct) {
                return 0;
            }

            if (this.selectedProduct.has_variants) {
                const variant = this.selectedProduct.variants.find((v) => v.id === this.selectedVariantId);

                return variant ? variant.price : 0;
            }

            return this.selectedProduct.price;
        },

        selectedPriceLabel() {
            return this.selectedPrice().toFixed(2).replace('.', ',');
        },

        formatMoney(value) {
            return 'R$ ' + Number(value).toFixed(2).replace('.', ',');
        },

        async refreshCart() {
            if (!this.summaryUrl) {
                return;
            }

            try {
                const res = await fetch(this.summaryUrl, {
                    headers: { Accept: 'application/json' },
                });

                if (res.ok) {
                    const data = await res.json();
                    this.cartCount = data.count;
                    this.cartTotal = data.total;
                }
            } catch (e) {
                //
            }
        },

        async addProduct() {
            if (!this.selectedProduct || this.adding) {
                return;
            }

            if (this.selectedProduct.has_variants && !this.selectedVariantId) {
                alert('Selecione o tamanho.');

                return;
            }

            this.adding = true;

            try {
                const body = new FormData();
                body.append('_token', this.csrf);
                body.append('product_id', this.selectedProduct.id);
                body.append('quantity', this.qty);

                if (this.selectedProduct.has_variants && this.selectedVariantId) {
                    body.append('variant_id', this.selectedVariantId);
                }

                if (this.notes) {
                    body.append('notes', this.notes);
                }

                if (this.comanda) {
                    body.append('comanda_number', this.comanda);
                }

                const res = await fetch(this.addUrl, {
                    method: 'POST',
                    body,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.message || 'Erro ao adicionar');
                }

                this.cartCount = data.cart_count;
                this.cartTotal = data.cart_total;
                this.productOpen = false;
            } catch (e) {
                alert(e.message || 'Não foi possível adicionar o item.');
            } finally {
                this.adding = false;
            }
        },

        async submitOrder() {
            if (this.cartCount === 0 || this.submitting || !this.comanda) {
                return;
            }

            this.submitting = true;

            try {
                const body = new FormData();
                body.append('_token', this.csrf);
                body.append('comanda_number', this.comanda);

                if (this.returnUrl) {
                    body.append('return_url', this.returnUrl);
                }

                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    body,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.message || 'Erro ao enviar');
                }

                if (data.reload && data.print_url) {
                    window.open(data.print_url, '_blank', 'noopener,noreferrer');
                    window.location.reload();
                } else if (data.reload) {
                    window.location.reload();
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } catch (e) {
                alert(e.message || 'Não foi possível enviar o pedido.');
            } finally {
                this.submitting = false;
            }
        },
    }));
}
