class FileManager {
    constructor() {
        this.initializeEventListeners();
        this.loadUserFiles();
    }

    initializeEventListeners() {
        const uploadForm = document.getElementById('uploadForm');
        const fileInput = document.getElementById('fileInput');
        const modal = document.getElementById('linkModal');
        const closeBtn = document.querySelector('.close');

        uploadForm.addEventListener('submit', (e) => this.handleUpload(e));
        fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        closeBtn.addEventListener('click', () => this.closeModal());
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal();
            }
        });
    }

    handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            document.querySelector('.file-text').textContent = ${files.length} فایل انتخاب شده;
        }
    }

    async handleUpload(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('fileInput');
        const files = fileInput.files;
        
        if (files.length === 0) {
            alert('لطفاً فایلی انتخاب کنید');
            return;
        }

        const uploadBtn = document.querySelector('.upload-btn');
        const progressSection = document.getElementById('progressSection');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        uploadBtn.disabled = true;
        progressSection.style.display = 'block';

        const formData = new FormData();
        for (let file of files) {
            // بررسی حجم فایل (20MB)
            if (file.size > 20 * 1024 * 1024) {
                alert(فایل "${file.name}" بزرگتر از 20 مگابایت است);
                continue;
            }
            formData.append('files[]', file);
        }

        try {
            const response = await this.uploadWithProgress(
                'api.php?action=upload',
                formData,
                (progress) => {
                    progressFill.style.width = ${progress}%;
                    progressText.textContent = در حال آپلود... ${Math.round(progress)}%;
                }
            );

            const result = await response.json();
            
            if (result.success) {
                this.showLinks(result.links);
                this.loadUserFiles();
            } else {
                alert('خطا در آپلود فایل: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('خطا در آپلود فایل');
        } finally {
            uploadBtn.disabled = false;
            progressSection.style.display = 'none';
            progressFill.style.width = '0%';
            fileInput.value = '';
            document.querySelector('.file-text').textContent = 'انتخاب فایل‌ها';
        }
    }

    uploadWithProgress(url, formData, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    onProgress(percentComplete);
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    resolve(xhr);
                } else {
                    reject(new Error('Upload failed'));
                }
            });

            xhr.addEventListener('error', () => reject(new Error('Upload failed')));
            xhr.open('POST', url);
            xhr.send(formData);
        });
                  }
  showLinks(links) {
        const modal = document.getElementById('linkModal');
        const linksContainer = document.getElementById('linksContainer');
        
        linksContainer.innerHTML = '';
        
        links.forEach(link => {
            const linkItem = document.createElement('div');
            linkItem.className = 'link-item';
            linkItem.innerHTML = 
                <strong>${link.name}</strong><br>
                <a href="${link.url}" target="_blank">${link.url}</a>
            ;
            linksContainer.appendChild(linkItem);
        });
        
        modal.style.display = 'block';
    }

    closeModal() {
        const modal = document.getElementById('linkModal');
        modal.style.display = 'none';
    }

    async loadUserFiles() {
        try {
            const response = await fetch('api.php?action=getFiles');
            const result = await response.json();
            
            if (result.success) {
                this.displayFiles(result.files);
                this.updateStats(result.stats);
            }
        } catch (error) {
            console.error('Error loading files:', error);
        }
    }

    displayFiles(files) {
        const filesList = document.getElementById('filesList');
        
        if (files.length === 0) {
            filesList.innerHTML = '<p style="text-align: center; color: #666;">هنوز فایلی آپلود نکرده‌اید</p>';
            return;
        }

        filesList.innerHTML = files.map(file => 
            <div class="file-item">
                <div class="file-info">
                    <span class="file-icon-small">${this.getFileIcon(file.type)}</span>
                    <div>
                        <div class="file-name">${file.name}</div>
                        <div class="file-size">${this.formatFileSize(file.size)}</div>
                    </div>
                </div>
                <div class="file-actions">
                    <a href="${file.url}" class="download-btn" target="_blank">📥 دانلود</a>
                    <button class="delete-btn" onclick="fileManager.deleteFile('${file.id}')">❌ حذف</button>
                </div>
            </div>
        ).join('');
    }

    getFileIcon(fileType) {
        const icons = {
            'image': '🏞',
            'video': '📽',
            'audio': '🎧',
            'document': '📄'
        };
        
        return icons[fileType] || '📁';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    updateStats(stats) {
        document.getElementById('totalFiles').textContent = stats.totalFiles;
        document.getElementById('totalSize').textContent = stats.totalSize;
    }

    async deleteFile(fileId) {
        if (!confirm('آیا از حذف این فایل مطمئن هستید؟')) {
            return;
        }

        try {
            const response = await fetch('api.php?action=delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ fileId: fileId })
            });

            const result = await response.json();
            
            if (result.success) {
                this.loadUserFiles();
            } else {
                alert('خطا در حذف فایل');
            }
        } catch (error) {
            console.error('Error deleting file:', error);
            alert('خطا در حذف فایل');
        }
    }
}

// Initialize the file manager when page loads
const fileManager = new FileManager();
