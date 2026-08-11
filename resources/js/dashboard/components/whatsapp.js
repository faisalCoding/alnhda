import { request } from '../api';

const POLL_INTERVAL = 5000;

export default function whatsappPage() {
    return {
        status: 'loading',
        message: 'جاري التحقق من حالة الواتساب...',
        qrImage: null,
        clientId: '',
        busy: false,
        timer: null,

        init() {
            this.checkStatus();
            this.timer = setInterval(() => {
                if (!this.busy && this.status !== 'ready' && !document.hidden) {
                    this.checkStatus();
                }
            }, POLL_INTERVAL);

            this.$el.addEventListener('alpine:destroyed', () => clearInterval(this.timer));
        },

        async checkStatus() {
            if (!navigator.onLine) {
                this.status = 'error';
                this.message = 'لا يوجد اتصال بالإنترنت.';

                return;
            }

            try {
                const payload = await request('GET', '/api/whatsapp/status');

                this.status = payload?.data?.status ?? 'unknown';
                this.message = payload?.data?.message ?? '';
                this.qrImage = payload?.data?.qr_image ?? null;
                this.clientId = payload?.data?.client_id ?? '';
            } catch (error) {
                this.status = 'error';
                this.message = error.message;
            }
        },

        async disconnect() {
            await this.command('/api/whatsapp/disconnect');
        },

        async startService() {
            await this.command('/api/whatsapp/start');
        },

        async resetSession() {
            if (!confirm('سيتم حذف الجلسة بالكامل وستحتاج إلى مسح رمز QR من جديد. هل أنت متأكد؟')) {
                return;
            }

            await this.command('/api/whatsapp/reset');
        },

        async command(url) {
            this.busy = true;

            try {
                const payload = await request('POST', url, {});

                this.status = payload?.data?.status ?? 'starting';
                this.message = payload?.data?.message ?? '';
                this.qrImage = null;
            } catch (error) {
                this.status = 'error';
                this.message = error.message;
            } finally {
                this.busy = false;
            }

            this.checkStatus();
        },

        get headline() {
            return {
                ready: 'متصل بالواتساب',
                needs_scan: 'بانتظار مسح رمز QR',
                loading: 'جاري مزامنة المحادثات…',
                starting: 'جاري التهيئة…',
                disconnected: 'انقطع الاتصال',
            }[this.status] ?? 'خدمة الواتساب غير متصلة';
        },
    };
}
