import {
    Award,
    BarChart3,
    Bot,
    BookOpen,
    Calendar,
    CalendarClock,
    Camera,
    ClipboardList,
    FileText,
    Gift,
    Globe,
    IdCard,
    ListOrdered,
    Mail,
    MapPin,
    MessageCircle,
    MessageSquareText,
    Package,
    QrCode,
    Receipt,
    Share2,
    ShoppingCart,
    Target,
    Ticket,
    Users,
    type LucideIcon,
} from 'lucide-react';

/**
 * Vendor-neutral line icons per service key (design.md §8: no
 * skeuomorphic/brand-recognizable icon set). Falls back to a generic glyph
 * for any service not explicitly mapped, so new catalog entries never break.
 */
const SERVICE_ICONS: Record<string, LucideIcon> = {
    'online-booking-system': Calendar,
    'business-website': Globe,
    'online-store': ShoppingCart,
    'digital-menu-price-list-qr': QrCode,
    'gift-cards-vouchers': Gift,
    'loyalty-rewards': Award,
    'reviews-reputation': MessageSquareText,
    'local-visibility-package': MapPin,
    'email-marketing-newsletters': Mail,
    'sms-messaging-campaigns': MessageCircle,
    'social-media-content-pack': Share2,
    'customer-database-crm-lite': Users,
    'invoicing-quotes': Receipt,
    'staff-scheduling': CalendarClock,
    'ai-customer-assistant-chatbot': Bot,
    'ai-knowledge-document-assistant-rag': BookOpen,
    'onboarding-intake-assistant': ClipboardList,
    'event-ticketing-registrations': Ticket,
    'membership-subscriptions-portal': IdCard,
    'waitlist-queue-management': ListOrdered,
    'lead-capture-follow-up': Target,
    'analytics-insights-dashboard': BarChart3,
    'content-digitization': FileText,
    'photography-media-brief': Camera,
};

export function serviceIcon(key: string | null | undefined): LucideIcon {
    return (key && SERVICE_ICONS[key]) || Package;
}
