import { uploadWithProgress } from './api';
import { uuid } from './ids';

export const uploadsStore = {
    tasks: [],

    get active() {
        return this.tasks.filter((task) => task.status === 'uploading');
    },

    /**
     * Upload a file with live progress. Rejects immediately while offline.
     */
    start({ url, formData, label }) {
        if (!navigator.onLine) {
            return Promise.reject(new Error('لا يمكن رفع الملفات دون اتصال بالإنترنت'));
        }

        const task = {
            key: uuid(),
            label,
            progress: 0,
            status: 'uploading',
            error: null,
        };

        this.tasks.push(task);

        return uploadWithProgress(url, formData, (progress) => {
            task.progress = progress;
        })
            .then((payload) => {
                task.status = 'done';
                task.progress = 100;
                setTimeout(() => this.dismiss(task.key), 4000);

                return payload;
            })
            .catch((error) => {
                task.status = 'failed';
                task.error = error.message;

                throw error;
            });
    },

    dismiss(key) {
        this.tasks = this.tasks.filter((task) => task.key !== key);
    },
};
