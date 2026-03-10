// app/src/utils/paymentMethod.js

// Import payment method logos
import visaLogo from '@/assets/images/icons/payment-methods/visa.svg';
import masterCardLogo from '@/assets/images/icons/payment-methods/master-card.svg';
import amexLogo from '@/assets/images/icons/payment-methods/amex.svg';
import discoverLogo from '@/assets/images/icons/payment-methods/discover.svg';
import jcbLogo from '@/assets/images/icons/payment-methods/jcb.svg';
import maestroLogo from '@/assets/images/icons/payment-methods/maestro.svg';
import dinersClubLogo from '@/assets/images/icons/payment-methods/diners_club_international.svg';
import chinaUnionPayLogo from '@/assets/images/icons/payment-methods/china_union_pay.svg';
import googlePayLogo from '@/assets/images/icons/payment-methods/google_pay.svg';
import applePayLogo from '@/assets/images/icons/payment-methods/apple-pay.svg';
import paypalLogo from '@/assets/images/icons/payment-methods/paypal.svg';
import afterpayLogo from '@/assets/images/icons/payment-methods/afterpay.svg';
import genericCardLogo from '@/assets/images/icons/payment-methods/credit-card.svg';

export function getPaymentMethodLogo(row) {
    // Get card details from meta
    const cardDetails = getMetaValue(row, '_braintree_card_details') || {};
    const paymentMethod = row.payment_method || '';

    const cardType = cardDetails.cardType || '';
    const paymentType = cardDetails.paymentType || '';

    // Card type mapping to imported logos
    const cardLogos = {
        'Visa': visaLogo,
        'Amex': amexLogo,
        'American Express': amexLogo,
        'Discover': discoverLogo,
        'MasterCard': masterCardLogo,
        'JCB': jcbLogo,
        'Maestro': maestroLogo,
        'Diners Club': dinersClubLogo,
        'Union Pay': chinaUnionPayLogo,
    };

    let logoUrl = '';
    let altText = 'Payment Method';

    // Special payment types
    if (paymentType === 'google_p') {
        logoUrl = googlePayLogo;
        altText = 'Google Pay';
    } else if (paymentType === 'apple_p') {
        logoUrl = applePayLogo;
        altText = 'Apple Pay';
    } else if (paymentType === 'paypal') {
        logoUrl = paypalLogo;
        altText = 'PayPal';
    } else if (cardType && cardLogos[cardType]) {
        logoUrl = cardLogos[cardType];
        altText = cardType;
    } else if (paymentMethod) {
        // Fallback: try to determine from payment method string
        const methodLower = paymentMethod.toLowerCase();
        if (methodLower.includes('paypal')) {
            logoUrl = paypalLogo;
            altText = 'PayPal';
        } else if (methodLower.includes('apple')) {
            logoUrl = applePayLogo;
            altText = 'Apple Pay';
        } else if (methodLower.includes('google')) {
            logoUrl = googlePayLogo;
            altText = 'Google Pay';
        } else if (methodLower.includes('afterpay')) {
            logoUrl = afterpayLogo;
            altText = 'Afterpay';
        }
    }

    // If no specific logo found, use generic credit card icon
    if (!logoUrl) {
        logoUrl = genericCardLogo;
        altText = 'Credit Card';
    }

    return { logoUrl, altText, cardType, paymentType };
}

function getMetaValue(row, key) {
    return (row.meta_data || []).find((m) => m.key === key)?.value || null;
}
