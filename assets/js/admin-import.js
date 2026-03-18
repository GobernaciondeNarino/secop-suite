/**
 * Elements API Data Upload - Admin JavaScript
 * 
 * @package ElementsAPIDataUpload
 * @version 3.0.0
 */

(function($) {
    'use strict';

    /**
     * Import Manager
     */
    const ImportManager = {
        isRunning: false,
        checkInterval: null,

        init: function() {
            this.bindEvents();
            this.checkInitialState();
        },

        bindEvents: function() {
            $('#ss-start-import').on('click', this.startImport.bind(this));
            $('#ss-cancel-import').on('click', this.cancelImport.bind(this));
        },

        checkInitialState: function() {
            // Verificar si hay una importación en curso al cargar la página
            this.checkProgress();
        },

        startImport: function(e) {
            e.preventDefault();

            if (this.isRunning) {
                return;
            }

            const $button = $('#ss-start-import');
            const $cancelButton = $('#ss-cancel-import');
            const $progressContainer = $('#ss-progress-container');
            const $resultContainer = $('#ss-import-result');

            // UI feedback
            $button.prop('disabled', true).addClass('updating-message');
            $cancelButton.show();
            $progressContainer.show();
            $resultContainer.hide();

            this.updateProgress(0, 0, 'starting', secopSuiteAdmin.strings.importing);

            // Iniciar importación via AJAX
            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_start_import',
                    nonce: secopSuiteAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.isRunning = true;
                        this.startProgressCheck();
                    } else {
                        this.showError(response.data.message);
                        this.resetUI();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error de conexión: ' + error);
                    this.resetUI();
                }
            });
        },

        cancelImport: function(e) {
            e.preventDefault();

            if (!confirm(secopSuiteAdmin.strings.confirm_cancel)) {
                return;
            }

            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_cancel_import',
                    nonce: secopSuiteAdmin.nonce
                },
                success: (response) => {
                    this.stopProgressCheck();
                    this.isRunning = false;
                    this.showMessage('warning', response.data.message);
                    this.resetUI();
                }
            });
        },

        startProgressCheck: function() {
            this.checkInterval = setInterval(() => {
                this.checkProgress();
            }, 2000);
        },

        stopProgressCheck: function() {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
            }
        },

        checkProgress: function() {
            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_check_progress',
                    nonce: secopSuiteAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        
                        this.updateProgress(
                            data.processed,
                            data.total,
                            data.status,
                            data.message
                        );

                        // Manejar estados finales
                        if (data.status === 'complete') {
                            this.stopProgressCheck();
                            this.isRunning = false;
                            this.showMessage('success', data.message);
                            this.resetUI();
                            // Recargar después de completar
                            setTimeout(() => location.reload(), 2000);
                        } else if (data.status === 'error' || data.status === 'cancelled') {
                            this.stopProgressCheck();
                            this.isRunning = false;
                            this.showMessage('error', data.message);
                            this.resetUI();
                        } else if (data.status === 'running') {
                            this.isRunning = true;
                            $('#ss-start-import').prop('disabled', true);
                            $('#ss-cancel-import').show();
                            $('#ss-progress-container').show();
                        }
                    }
                }
            });
        },

        updateProgress: function(processed, total, status, message) {
            const $fill = $('#ss-progress-fill');
            const $text = $('#ss-progress-text');
            
            let percentage = 0;
            if (total > 0) {
                percentage = Math.round((processed / total) * 100);
            }

            $fill.css('width', percentage + '%');
            
            let displayText = message;
            if (status === 'running' && total > 0) {
                displayText = `${message} (${processed.toLocaleString()} / ${total.toLocaleString()} - ${percentage}%)`;
            }
            
            $text.text(displayText);
        },

        showMessage: function(type, message) {
            const $container = $('#ss-import-result');
            $container
                .removeClass('ss-notice-success ss-notice-error ss-notice-warning ss-notice-info')
                .addClass('ss-notice-' + type)
                .html(message)
                .show();
        },

        showError: function(message) {
            this.showMessage('error', message);
        },

        resetUI: function() {
            const $button = $('#ss-start-import');
            const $cancelButton = $('#ss-cancel-import');
            
            $button.prop('disabled', false).removeClass('updating-message');
            $cancelButton.hide();
        }
    };

    /**
     * Settings Manager
     */
    const SettingsManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Toggle de opciones de actualización automática
            $('#secop_suite_auto_update_enabled').on('change', function() {
                const $options = $('.ss-auto-update-options');
                if ($(this).is(':checked')) {
                    $options.slideDown();
                } else {
                    $options.slideUp();
                }
            });
        }
    };

    /**
     * Records Manager
     */
    const RecordsManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Ver detalles del contrato
            $('.ss-view-details').on('click', this.viewDetails.bind(this));
            
            // Cerrar modal
            $('.ss-modal-close').on('click', this.closeModal);
            
            // Cerrar modal con click fuera
            $(document).on('click', '.ss-modal', function(e) {
                if ($(e.target).hasClass('ss-modal')) {
                    RecordsManager.closeModal();
                }
            });
            
            // Cerrar modal con Escape
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    RecordsManager.closeModal();
                }
            });
        },

        viewDetails: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const contractId = $button.data('id');
            const $modal = $('#ss-detail-modal');
            const $content = $('#ss-detail-content');
            
            // Mostrar loading
            $content.html('<div class="ss-loading"><span class="spinner is-active"></span> Cargando...</div>');
            $modal.show();
            
            // Cargar datos via REST API
            $.ajax({
                url: '/wp-json/secop-suite/v1/contracts/' + contractId,
                type: 'GET',
                success: function(data) {
                    $content.html(RecordsManager.renderDetails(data));
                },
                error: function() {
                    $content.html('<div class="ss-error">Error al cargar los detalles del contrato.</div>');
                }
            });
        },

        renderDetails: function(contract) {
            const formatCurrency = (value) => {
                if (!value) return '$0';
                return '$' + parseFloat(value).toLocaleString('es-CO', { minimumFractionDigits: 0 });
            };

            const formatDate = (date) => {
                if (!date) return '-';
                return new Date(date).toLocaleDateString('es-CO');
            };

            return `
                <div class="ss-detail-grid">
                    <div class="ss-detail-section">
                        <h3>Información General</h3>
                        <table class="ss-detail-table">
                            <tr><th>ID Contrato</th><td>${contract.id_contrato || '-'}</td></tr>
                            <tr><th>Referencia</th><td>${contract.referencia_del_contrato || '-'}</td></tr>
                            <tr><th>Proceso de Compra</th><td>${contract.proceso_de_compra || '-'}</td></tr>
                            <tr><th>Estado</th><td><span class="ss-estado">${contract.estado_contrato || '-'}</span></td></tr>
                            <tr><th>Tipo de Contrato</th><td>${contract.tipo_de_contrato || '-'}</td></tr>
                            <tr><th>Modalidad</th><td>${contract.modalidad_de_contratacion || '-'}</td></tr>
                            <tr><th>Justificación</th><td>${contract.justificacion_modalidad_de || '-'}</td></tr>
                        </table>
                    </div>
                    
                    <div class="ss-detail-section">
                        <h3>Proveedor</h3>
                        <table class="ss-detail-table">
                            <tr><th>Nombre</th><td>${contract.proveedor_adjudicado || '-'}</td></tr>
                            <tr><th>Documento</th><td>${contract.tipodocproveedor || ''} ${contract.documento_proveedor || '-'}</td></tr>
                            <tr><th>Es PYME</th><td>${contract.es_pyme || '-'}</td></tr>
                            <tr><th>Es Grupo</th><td>${contract.es_grupo || '-'}</td></tr>
                        </table>
                    </div>
                    
                    <div class="ss-detail-section">
                        <h3>Fechas</h3>
                        <table class="ss-detail-table">
                            <tr><th>Fecha de Firma</th><td>${formatDate(contract.fecha_de_firma)}</td></tr>
                            <tr><th>Fecha Inicio</th><td>${formatDate(contract.fecha_de_inicio_del_contrato)}</td></tr>
                            <tr><th>Fecha Fin</th><td>${formatDate(contract.fecha_de_fin_del_contrato)}</td></tr>
                            <tr><th>Días Adicionados</th><td>${contract.dias_adicionados || 0}</td></tr>
                        </table>
                    </div>
                    
                    <div class="ss-detail-section">
                        <h3>Valores</h3>
                        <table class="ss-detail-table">
                            <tr><th>Valor del Contrato</th><td><strong>${formatCurrency(contract.valor_del_contrato)}</strong></td></tr>
                            <tr><th>Valor Pagado</th><td>${formatCurrency(contract.valor_pagado)}</td></tr>
                            <tr><th>Valor Facturado</th><td>${formatCurrency(contract.valor_facturado)}</td></tr>
                            <tr><th>Valor Pendiente de Pago</th><td>${formatCurrency(contract.valor_pendiente_de_pago)}</td></tr>
                            <tr><th>Valor Pendiente Ejecución</th><td>${formatCurrency(contract.valor_pendiente_de_ejecucion)}</td></tr>
                        </table>
                    </div>
                    
                    <div class="ss-detail-section ss-detail-full">
                        <h3>Descripción</h3>
                        <p>${contract.descripcion_del_proceso || 'Sin descripción'}</p>
                    </div>
                    
                    ${contract.urlproceso ? `
                    <div class="ss-detail-section ss-detail-full">
                        <h3>Enlace SECOP</h3>
                        <a href="${contract.urlproceso}" target="_blank" class="button button-primary">
                            Ver en SECOP <span class="dashicons dashicons-external"></span>
                        </a>
                    </div>
                    ` : ''}
                </div>
            `;
        },

        closeModal: function() {
            $('#ss-detail-modal').hide();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Inicializar managers según la página
        if ($('#ss-start-import').length) {
            ImportManager.init();
            SettingsManager.init();
        }
        
        if ($('.ss-records-table').length) {
            RecordsManager.init();
        }
    });

})(jQuery);

/* Additional styles for details modal */
const detailStyles = `
<style>
.ss-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.ss-detail-section {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}

.ss-detail-section h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #1d2327;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}

.ss-detail-full {
    grid-column: 1 / -1;
}

.ss-detail-table {
    width: 100%;
    font-size: 13px;
}

.ss-detail-table th {
    text-align: left;
    padding: 5px 10px 5px 0;
    color: #666;
    font-weight: normal;
    width: 40%;
}

.ss-detail-table td {
    padding: 5px 0;
}

.ss-loading {
    text-align: center;
    padding: 40px;
}

.ss-loading .spinner {
    float: none;
    margin: 0 10px 0 0;
}

@media (max-width: 600px) {
    .ss-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', detailStyles);
