

import Alpine from 'alpinejs';
import { registerProductPickerModal } from './alpine/product-picker-modal';
import { registerRecipeForm } from './alpine/recipe-form';

window.Alpine = Alpine;

registerProductPickerModal(Alpine);
registerRecipeForm(Alpine);

Alpine.start();
