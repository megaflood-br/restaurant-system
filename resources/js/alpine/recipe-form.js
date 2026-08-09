export function registerRecipeForm(Alpine) {
    Alpine.data('recipeForm', (initialLines = [], ingredientMeta = {}, yieldPortions = 1) => {
        const lines = (Array.isArray(initialLines) && initialLines.length
            ? initialLines
            : [{ ingredient_id: '', quantity: '' }]
        ).map((line) => ({
            ingredient_id: line.ingredient_id != null && line.ingredient_id !== ''
                ? String(line.ingredient_id)
                : '',
            quantity: line.quantity ?? '',
        }));

        return {
            lines,
            ingredientMeta,
            yieldPortions: yieldPortions || 1,

            unitLabel(ingredientId) {
                return this.ingredientMeta[ingredientId]?.recipeLabel ?? 'g';
            },

            toStockQuantity(recipeQty, meta) {
                const quantity = parseFloat(recipeQty) || 0;

                if (meta.stockUnit === 'kg' || meta.stockUnit === 'L') {
                    return quantity / 1000;
                }

                return quantity;
            },

            lineCost(line) {
                const meta = this.ingredientMeta[line.ingredient_id];

                if (! meta || ! line.quantity) {
                    return 0;
                }

                return this.toStockQuantity(line.quantity, meta) * (meta.unitCost || 0);
            },

            totalCost() {
                return this.lines.reduce((sum, line) => sum + this.lineCost(line), 0);
            },

            costPerPortion() {
                const yieldValue = parseInt(this.yieldPortions, 10) || 1;

                return this.totalCost() / Math.max(1, yieldValue);
            },

            formatMoney(value) {
                return 'R$ ' + Number(value || 0).toFixed(2).replace('.', ',');
            },

            addLine() {
                this.lines.push({ ingredient_id: '', quantity: '' });
            },

            removeLine(index) {
                this.lines.splice(index, 1);

                if (! this.lines.length) {
                    this.addLine();
                }
            },
        };
    });
}
