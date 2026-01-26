import { useEffect, useState } from "react";
import { useHotkeys } from "react-hotkeys-hook";

export default function Modalsetclaveadmin({
    inputsetclaveadminref,
    valinputsetclaveadmin,
    setvalinputsetclaveadmin,
    closemodalsetclave,
    sendClavemodal,
    typingTimeout,
    setTypingTimeout,
}) {
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        if (inputsetclaveadminref.current) {
            inputsetclaveadminref.current.focus();
        }
    }, []);

    const removeInput = () => {
        if (typingTimeout != 0) {
            clearTimeout(typingTimeout);
        }

        let time = window.setTimeout(() => {
            setvalinputsetclaveadmin("");
            setError("");
        }, 300); // Reducido a 300ms para mayor seguridad
        setTypingTimeout(time);
    };

    // Limpiar contraseña al perder el foco
    const handleBlur = () => {
        setTimeout(() => {
            setvalinputsetclaveadmin("");
        }, 100);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!valinputsetclaveadmin.trim()) {
            setError("Por favor ingrese la clave");
            return;
        }
        sendClavemodal();
    };

    useHotkeys(
        "esc",
        (event) => {
            closemodalsetclave();
        },
        {
            filterPreventDefault: false,
            enableOnTags: ["INPUT", "SELECT", "TEXTAREA"],
        },
        []
    );

    return (
        <>
            <section className="modal-custom">
                <div className="shadow-lg modal-content-supersm">
                    <div className="pb-0 border-0 modal-header">
                        <h5 className="modal-title">
                            <i className="fa fa-lock text-primary me-2"></i>
                            Acceso Administrativo
                        </h5>
                        <button 
                            type="button" 
                            className="btn-close" 
                            onClick={closemodalsetclave}
                            aria-label="Cerrar"
                        ></button>
                    </div>
                    <div className="modal-body">
                        <form onSubmit={handleSubmit}>
                            <div className="mb-3">
                                <label className="form-label text-muted">
                                    Ingrese la clave de administrador
                                </label>
                                <div className="input-group">
                                    <input
                                        type={showPassword ? "text" : "password"}
                                        className={`form-control form-control-lg ${error ? 'is-invalid' : ''}`}
                                        ref={inputsetclaveadminref}
                                        value={valinputsetclaveadmin}
                                        onChange={e => {
                                            setvalinputsetclaveadmin(e.target.value);
                                            removeInput();
                                            setError("");
                                        }}
                                        onBlur={handleBlur}
                                        onPaste={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        onCopy={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        onCut={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        onContextMenu={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        onDrag={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            return false;
                                        }}
                                        autoComplete="new-password"
                                        autoSave="off"
                                        autoCapitalize="off"
                                        autoCorrect="off"
                                        spellCheck="false"
                                        data-lpignore="true"
                                        data-form-type="other"
                                        data-1p-ignore="true"
                                        data-bwignore="true"
                                        data-kwignore="true"
                                        placeholder="Ingrese la clave"
                                        autoFocus
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary"
                                        onClick={() => setShowPassword(!showPassword)}
                                    >
                                        <i className={`fa ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                                    </button>
                                </div>
                                {error && (
                                    <div className="invalid-feedback d-block">
                                        {error}
                                    </div>
                                )}
                            </div>
                            <div className="gap-2 d-flex justify-content-end">
                                <button
                                    type="button"
                                    className="btn btn-outline-secondary"
                                    onClick={closemodalsetclave}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                >
                                    <i className="fa fa-check me-1"></i>
                                    Acceder
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <div className="overlay"></div>
            <style>{`
                .modal-custom {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 1050;
                }
                .modal-content-supersm {
                    background: white;
                    border-radius: 0.5rem;
                    padding: 1.5rem;
                    min-width: 400px;
                }
                .overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(4px);
                    z-index: 1040;
                }
                .form-control:focus {
                    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
                }
                .btn-close:focus {
                    box-shadow: none;
                }
            `}</style>
        </>
    );
}