<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
const route = useRoute()
const pages: Record<string, { title: string; sections: { title: string; text: string }[] }> = {
  about: { title: 'About E-KHMER', sections: [{ title: 'A marketplace for independent shops', text: 'E-KHMER brings shop catalogs, product options, ordering, and delivery tracking into one place. Visit a shop page to see its products and contact details.' }] },
  faq: { title: 'Frequently asked questions', sections: [
    { title: 'How do I buy a product?', text: 'Choose your product options, add the item to your cart, and enter your delivery address at checkout. Review all prices and delivery charges before placing your order.' },
    { title: 'How does Bakong payment work?', text: 'Each shop receives its own payment directly. Scan the QR shown for each shop, then use Check payment. The server verifies the recipient, currency, amount, and transaction before confirming the order.' },
    { title: 'What if my payment window expires?', text: 'Do not send a new payment to an expired QR. Use Check payment to reconcile a payment already sent. If the order was cancelled after a payment, contact support and the seller for a verified refund.' },
    { title: 'Where can I track my order?', text: 'Open My Orders from your account. Orders containing several shops show separate delivery progress for each shop.' }
  ] },
  shipping: { title: 'Shipping and returns', sections: [
    { title: 'Delivery', text: 'Available delivery methods, prices, and estimated delivery times are shown during checkout. Multi-shop orders may arrive in separate shipments. Contact the seller or support with your order number if you need help.' },
    { title: 'Requesting a return', text: 'Open Returns and refunds in your account, find your delivered order, choose the item and quantity, and describe the reason. You may attach up to three evidence photos. Eligibility is checked against the store’s configured return window.' },
    { title: 'Refund status', text: 'The seller reviews the request and confirms receipt of returned goods. A Bakong refund is completed only after the seller sends it and the server verifies the bank transaction. Cash-on-delivery refunds use a recorded cash receipt. Changing a status does not itself transfer money.' }
  ] },
  privacy: { title: 'Privacy information', sections: [
    { title: 'Information used for your order', text: 'Your account, contact details, addresses, cart, order history, and payment references are used to operate the service. The shop fulfilling your items receives the information needed to deliver and support your order.' },
    { title: 'Photos and support requests', text: 'Product photos appear publicly. Return evidence photos are restricted to the requesting customer and authorized shop or platform staff. Do not include passwords, payment PINs, or sensitive personal documents in messages or photos.' },
    { title: 'Email preferences and requests', text: 'Newsletter subscriptions can be cancelled using the unsubscribe link in the subscription email. Contact support for questions about your information or account.' }
  ] },
  terms: { title: 'Marketplace information', sections: [
    { title: 'Independent shops', text: 'Products are offered by the shop identified on their product page. Review the product description, selected options, price, delivery details, and shop contact information before purchasing.' },
    { title: 'Payments and support', text: 'Bakong payments are made directly to the seller identified at checkout. Keep your order number and bank transaction reference. Use support and the return workflow to report an issue.' }
  ] }
}
const page = computed(() => pages[String(route.params.page)])
</script>
<template><main class="container-app max-w-3xl space-y-7 py-10"><template v-if="page"><h1 class="text-3xl font-bold">{{ page.title }}</h1><section v-for="section in page.sections" :key="section.title" class="card p-6"><h2 class="text-lg font-semibold">{{ section.title }}</h2><p class="mt-3 text-sm leading-relaxed text-muted">{{ section.text }}</p></section></template><h1 v-else class="text-2xl font-bold">Page not found</h1><div class="flex gap-4"><RouterLink to="/support" class="btn-primary">Contact support</RouterLink><RouterLink to="/shops" class="btn-secondary">Explore shops</RouterLink></div></main></template>

