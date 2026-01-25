import React from 'react';

function Cargando({ active = true, message = "Cargando..." }) {
	if (!active) return null;

	return (
		<div className="loading-overlay" role="alert" aria-busy="true">
			<div className="loading-container">
				<div className="loading-spinner">
					<div className="spinner-circle"></div>
					<div className="spinner-circle"></div>
					<div className="spinner-circle"></div>
				</div>
				<div className="loading-message">{message}</div>
			</div>
			<style>{`
				.loading-overlay {
					position: fixed;
					bottom: 20px;
					left: 50%;
					transform: translateX(-50%);
					background: rgba(255, 255, 255, 0.95);
					display: flex;
					justify-content: center;
					align-items: center;
					z-index: 9999;
					border-radius: 8px;
					box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
					padding: 12px 20px;
					backdrop-filter: blur(4px);
					border: 1px solid rgba(0, 0, 0, 0.1);
					animation: slideUp 0.3s ease-out;
				}

				.loading-container {
					display: flex;
					align-items: center;
					gap: 12px;
				}

				.loading-spinner {
					display: flex;
					gap: 4px;
				}

				.spinner-circle {
					width: 8px;
					height: 8px;
					border-radius: 50%;
					background-color: var(--sinapsis-color, #007bff);
					animation: bounce 0.5s ease-in-out infinite;
				}

				.spinner-circle:nth-child(2) {
					animation-delay: 0.1s;
				}

				.spinner-circle:nth-child(3) {
					animation-delay: 0.2s;
				}

				.loading-message {
					color: #2c3e50;
					font-size: 0.9rem;
					font-weight: 500;
					margin: 0;
				}

				@keyframes bounce {
					0%, 100% {
						transform: translateY(0);
					}
					50% {
						transform: translateY(-4px);
					}
				}

				@keyframes slideUp {
					from {
						transform: translate(-50%, 100%);
						opacity: 0;
					}
					to {
						transform: translate(-50%, 0);
						opacity: 1;
					}
				}
			`}</style>
		</div>
	);
}

export default Cargando;