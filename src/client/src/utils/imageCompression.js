/**
 * Compresses an image file to ensure it's under 2MB
 * @param {File} file - The image file to compress
 * @param {number} maxSizeKB - Maximum size in KB (default 2000KB = 2MB)
 * @param {number} quality - Initial quality (0-1, default 0.8)
 * @returns {Promise<File>} - Compressed file
 */
export const compressImage = async (file, maxSizeKB = 2000, quality = 0.8) => {
    return new Promise((resolve) => {
        // If it's not an image, return as-is
        if (!file.type.startsWith('image/')) {
            resolve(file);
            return;
        }

        // If it's already under the size limit, return as-is
        if (file.size <= maxSizeKB * 1024) {
            resolve(file);
            return;
        }

        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                // Calculate new dimensions if needed
                const maxDimension = 1920; // Max width/height
                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = (height * maxDimension) / width;
                        width = maxDimension;
                    } else {
                        width = (width * maxDimension) / height;
                        height = maxDimension;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                const compress = (currentQuality) => {
                    canvas.toBlob(
                        (blob) => {
                            if (!blob) {
                                resolve(file);
                                return;
                            }

                            // If blob is still too large and quality > 0.1, try lower quality
                            if (blob.size > maxSizeKB * 1024 && currentQuality > 0.1) {
                                const newQuality = Math.max(0.1, currentQuality - 0.1);
                                compress(newQuality);
                            } else {
                                // Create new File from blob
                                const compressedFile = new File([blob], file.name, {
                                    type: file.type,
                                    lastModified: Date.now(),
                                });
                                resolve(compressedFile);
                            }
                        },
                        file.type,
                        currentQuality
                    );
                };

                compress(quality);
            };
        };
    });
};