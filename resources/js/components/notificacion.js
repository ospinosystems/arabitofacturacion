import { useEffect, useState } from 'react';

function Notificacion({ msj, notificar }) {
	const [isVisible, setIsVisible] = useState(true);
	const [isExiting, setIsExiting] = useState(false);

	useEffect(() => {
		if (msj) {
			setIsVisible(true);
			setIsExiting(false);
		}
	}, [msj]);

	const handleClose = () => {
		setIsExiting(true);
		setTimeout(() => {
			setIsVisible(false);
			notificar("");
		}, 300);
	};

	if (!msj || !isVisible) return null;

	return (
		<div className={`notification-container ${isExiting ? 'notification-exit' : 'notification-enter'}`}>
			<div className="notification-content">
				<div className="notification-header">
					<div className="notification-title">
						<i className="fa fa-bell text-primary me-2"></i>
						Notificación
					</div>
					<button 
						className="notification-close" 
						onClick={handleClose}
						title="Cerrar notificación"
					>
						<i className="fa fa-times"></i>
					</button>
				</div>
				<div className="notification-body">
					{msj}
				</div>
			</div>
			<style>{`
				.notification-container {
					position: fixed;
					bottom: 20px;
					right: 20px;
					z-index: 9999;
					min-width: 300px;
					max-width: 450px;
				}

				.notification-content {
					background: white;
					border-radius: 8px;
					box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
					overflow: hidden;
				}

				.notification-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 12px 16px;
					background: #f8f9fa;
					border-bottom: 1px solid #e9ecef;
				}

				.notification-title {
					font-weight: 600;
					color: #212529;
					display: flex;
					align-items: center;
				}

				.notification-close {
					background: none;
					border: none;
					color: #6c757d;
					padding: 4px 8px;
					cursor: pointer;
					transition: color 0.2s;
				}

				.notification-close:hover {
					color: #dc3545;
				}

				.notification-body {
					padding: 16px;
					color: #212529;
					font-size: 1rem;
					line-height: 1.5;
				}

				.notification-enter {
					animation: slideUp 0.3s ease-out;
				}

				.notification-exit {
					animation: slideDown 0.3s ease-in;
				}

				@keyframes slideUp {
					from {
						transform: translateY(100%);
						opacity: 0;
					}
					to {
						transform: translateY(0);
						opacity: 1;
					}
				}

				@keyframes slideDown {
					from {
						transform: translateY(0);
						opacity: 1;
					}
					to {
						transform: translateY(100%);
						opacity: 0;
					}
				}
			`}</style>
		</div>
	);
}

export default Notificacion;