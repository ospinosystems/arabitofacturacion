import React, { useState, useEffect } from 'react';
import db from '../database/database';

// Helper for number input
const parseNum = (val) => {
    if (val === "" || val === null || val === undefined) return 0;
    // Remove dots (thousand separators) and replace comma with dot
    let numStr = val.toString().replace(/\./g, '').replace(',', '.');
    const num = parseFloat(numStr);
    return isNaN(num) ? 0 : num;
};

// Format number as xxx.xxx,xx
const formatCurrency = (val) => {
    if (val === "" || val === null || val === undefined) return "";
    
    // Handle potential incoming number type
    let numStr = val.toString().replace(/\./g, '').replace(',', '.');
    let num = parseFloat(numStr);
    
    if (isNaN(num)) return "";

    // Format with dots for thousands and comma for decimals
    return num.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Component for formatted number input
const NumberInput = ({ value, onChange, placeholder, className, autoFocus, onKeyDown, isCurrency = false }) => {
    // Local state to handle typing "1000" without it immediately becoming "1.000,00" and messing up cursor or value during typing if not careful
    // However, for a "mask" effect, we usually process on change.
    
    const handleChange = (e) => {
        let rawVal = e.target.value;
        
        // Allow only numbers and comma/dot
        if (!/^[0-9.,]*$/.test(rawVal)) return;

        // Logic for live formatting is complex without a library.
        // User asked for "xxx.xxx,xx" format.
        // Simplest robust way: Parse raw input to valid float for state, but display formatted.
        // But input type="text" is needed for custom format.
        
        onChange(e);
    };

    // Using a simple number input with display formatting on blur is easier, 
    // but user asked "al escribir".
    // Let's use a standard input that formats.
    
    // Better approach for "al escribir" without library: 
    // Use CurrencyInput libraries usually.
    // Here, let's implement a simple cleaner that strips non-numeric, 
    // then formats. 
    
    // BUT, the previous component was type="number". 
    // To support "xxx.xxx,xx", we must use type="text".
    
    const [displayVal, setDisplayVal] = useState("");

    useEffect(() => {
        // When external value changes (e.g. from calculations), update display
        // Value passed in is a Float (e.g. 1500.50)
        // We want to display "1.500,50"
        if (value === "" || value === 0) {
             // Only clear if user hasn't typed? 
             // Actually if value is 0 coming from parent (init), show "".
             if (value === 0 && displayVal === "") return;
        }
        
        // Don't overwrite if user is typing (handled by local logic?)
        // This is the tricky part of controlled inputs with formatting.
        
        // Simple strategy: Just display what is passed, formatted.
        // But editing "1.500,00" is hard.
        
        // Alternative: Use a library. Since I can't install, 
        // I will use a simpler approach: 
        // Input takes text. On Change: strip non-digits. Treat as cents?
        // Or just standard "1234,56".
        
        // Let's stick to a solid Currency Input implementation logic:
        // 1. User types numbers.
        // 2. We format on blur? Or live?
        // "al escribir" implies live.
        
        // Let's assume the parent passes a Float.
        // We display formatted string.
    }, [value]);

    // Ref to handle cursor? No, simple replacement for now.
    
    // Let's use a simple implementation:
    // Type="text".
    // OnChange -> allow digits and one comma.
    // Parent receives the parsed float.
    
    return (
        <input 
            type="text"
            className={`block w-full px-2 py-1 rounded-r-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-right ${className}`}
            placeholder={placeholder}
            value={value} // Parent controls value. Parent should pass formatted string? No, parent has state as numbers/strings.
            // To support "xxx.xxx,xx", the parent state should ideally be the raw string or we handle conversion.
            // Current Parent State: caja.usd is numeric or string.
            // Let's change parent to handle masking if we change this.
            
            // Given the constraint: "format xxx.xxx,xx al escribir"
            // I will implement a formatter inside onChange.
            
            onChange={(e) => {
                let val = e.target.value;
                
                // Remove invalid chars (letters)
                val = val.replace(/[^0-9,]/g, '');
                
                // Ensure only one comma
                const parts = val.split(',');
                if (parts.length > 2) val = parts[0] + ',' + parts.slice(1).join('');
                
                // Add thousands separators (dots) to the integer part
                // Remove existing dots to re-calc
                // val = val.replace(/\./g, ''); 
                // Actually, the user input might have dots.
                
                // Let's defer complex masking and just do:
                // 1. Allow 0-9 and ,
                // 2. Parse to float for parent
                // 3. Let parent update.
                
                onChange(e); // Pass event with cleaned value?
            }}
            onKeyDown={onKeyDown}
            autoFocus={autoFocus}
        />
    );
};

// Customized Currency Input Component
const CurrencyInput = ({ value, onChange, className, placeholder, onKeyDown, autoFocus, disabled }) => {
    // Value is expected to be a number (float) from parent state
    // We maintain an internal string state for editing
    
    const [localVal, setLocalVal] = useState("");
    
    useEffect(() => {
        // Sync from parent if parent changes externally (e.g. auto-calc)
        // But avoid overriding user typing.
        // We'll assume parent updates are authoritative for "Totals" calculated from bills.
        // For manual inputs, parent updates immediately.
        
        if (value === 0 || value === "") {
             if (localVal === "") return;
             // If parent is 0 and local is not, it might be a reset.
             // setLocalVal(""); 
             // Don't aggressive reset.
        }
        
        // Check if parent value matches our parsed local value.
        // If yes, don't update (avoids cursor jumping).
        // If no, update (external change).
        const currentParsed = parseLocal(localVal);
        if (Math.abs(currentParsed - value) > 0.001) {
             // External update (e.g. from Bills)
             if (value === 0) setLocalVal("");
             else setLocalVal(value.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }
    }, [value]);

    const parseLocal = (str) => {
        if (!str) return 0;
        // Remove dots, replace comma with dot
        let clean = str.replace(/\./g, '').replace(',', '.');
        let num = parseFloat(clean);
        return isNaN(num) ? 0 : num;
    };

    const formatInput = (numStr) => {
        // Logic: 
        // 1. Remove non-numeric/comma
        // 2. Split integer/decimal
        // 3. Add dots to integer
        
        let clean = numStr.replace(/[^0-9,]/g, '');
        
        // Prevent multiple commas
        if ((clean.match(/,/g) || []).length > 1) {
            return localVal; // Ignore invalid input
        }
        
        let [int, dec] = clean.split(',');
        
        // Remove leading zeros from int unless it is just "0"
        if (int.length > 1 && int.startsWith('0')) int = int.substring(1);
        if (int === "") int = "0";
        
        // Add dots to integer part
        // Remove existing dots first? We already stripped them in clean step (replaced non 0-9,)
        
        // Add dots
        int = int.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        
        if (dec !== undefined) {
            // Limit decimals to 2? User didn't specify, but currency usually 2.
            if (dec.length > 2) dec = dec.substring(0, 2);
            return `${int},${dec}`;
        }
        
        return int;
    };

    const handleChange = (e) => {
        let val = e.target.value;
        
        // Handle "Backspace" on a dot or comma specially? 
        // React onChange doesn't give key type easily.
        
        // Basic cleaning
        let clean = val.replace(/[^0-9,]/g, '');
        
        // Live Formatting logic:
        // If user types '1', show '1'
        // If '1000', show '1.000'
        // If '1000,', show '1.000,'
        // If '1000,5', show '1.000,5'
        
        // Re-construct
        let parts = clean.split(',');
        let int = parts[0].replace(/\./g, ''); // Raw integer
        
        // Format Integer
        if (int !== "") {
            int = parseInt(int, 10).toLocaleString('es-VE'); // Uses dots
        }
        
        let newVal = int;
        if (parts.length > 1) {
            newVal += "," + parts[1].substring(0, 2);
        } else if (val.endsWith(',')) {
            newVal += ",";
        }
        
        setLocalVal(newVal);
        
        // Emit Float to parent
        onChange(parseLocal(newVal));
    };

    return (
        <input 
            type="text"
            className={`block w-full px-2 py-1 rounded-r-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-right ${className} ${disabled ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''}`}
            placeholder={placeholder}
            value={localVal}
            onChange={handleChange}
            onKeyDown={onKeyDown}
            autoFocus={autoFocus}
            disabled={disabled}
        />
    );
};

export default function CierreV2({ totalizarcierre = false, onClose, bancos }) {
    const [step, setStep] = useState(1); // 1: Input, 2: Review
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    
    // Cash Inputs
    const [billetes, setBilletes] = useState({
        1: "", 5: "", 10: "", 20: "", 50: "", 100: ""
    });
    
    // Totals Input
    const [caja, setCaja] = useState({ usd: "", cop: "", bs: "" });
    
    // Other Inputs
    const [lotes, setLotes] = useState([]);
    const [newLote, setNewLote] = useState({ banco: "", lote: "", monto: "", tipo: "DEBITO" });
    
    const [biopago, setBiopago] = useState({ serial: "", monto: "" });
    const [dejar, setDejar] = useState({ usd: "", cop: "", bs: "" });
    
    const [calcResult, setCalcResult] = useState(null);
    
    // Bank list is received as prop from parent (facturar.js)


    // Auto-calc cash from bills
    useEffect(() => {
        if (totalizarcierre) return; // Don't auto-calc for admin from local bills
        let total = 0;
        Object.keys(billetes).forEach(k => {
            total += (parseFloat(k) * parseNum(billetes[k]));
        });
        if (total > 0) setCaja(prev => ({...prev, usd: total}));
    }, [billetes, totalizarcierre]);

    const handleAddLote = () => {
        if (!newLote.banco || !newLote.lote || !newLote.monto) return alert("Complete los datos del lote");
        if (lotes.find(l => l.lote === newLote.lote)) return alert("Lote ya agregado");
        
        setLotes([...lotes, newLote]);
        setNewLote({ ...newLote, lote: "", monto: "" }); // Keep bank selected
    };

    const removeLote = (idx) => {
        setLotes(lotes.filter((_, i) => i !== idx));
    };

    const handleCalculate = () => {
        setLoading(true);
        setError(null);
        
        const total_punto = lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0);
        
        // Pass raw numbers (floats) to backend
        db.calcularCierreV2({
            totalizarcierre,
            caja_usd: caja.usd, // Already floats in state
            caja_cop: caja.cop,
            caja_bs: caja.bs,
            lotes,
            biopago_monto: biopago.monto,
            total_punto_input: total_punto,
        }).then(res => {
            setLoading(false);
            if (res.data.estado) {
                setCalcResult(res.data.data);
                
                // If totalizarcierre, update state with aggregated declared values from response
                if (totalizarcierre && res.data.data.cajeros_declarado) {
                    const decl = res.data.data.cajeros_declarado;
                    setCaja({
                        usd: decl.caja_usd,
                        cop: decl.caja_cop,
                        bs: decl.caja_bs
                    });
                    setBiopago({ serial: "", monto: decl.caja_biopago });
                    // Lotes sum is handled via total_punto_input usually, but we don't populate lotes array for Admin?
                    // Admin just sees total. But existing UI expects lotes array for "Declared" table?
                    // We can clear lotes and trust "total_punto_input" if passed.
                    // But my table uses `lotes.reduce`.
                    // Admin doesn't see individual lotes in inputs?
                    // Let's just assume Admin validates totals.
                }

                setStep(2);
            } else {
                setError(res.data.msj);
            }
        }).catch(err => {
            setLoading(false);
            console.error(err);
            setError("Error de conexión o servidor");
        });
    };
    
    // Auto-calculate for Admin on mount
    useEffect(() => {
        if (totalizarcierre) {
            handleCalculate();
        }
    }, []);

    const handleSave = () => {
        if (!window.confirm("¿Guardar Cierre?")) return;
        setLoading(true);
        db.guardarCierreV2({
            totalizarcierre,
            caja_usd: caja.usd,
            caja_cop: caja.cop,
            caja_bs: caja.bs,
            lotes,
            biopago_monto: biopago.monto,
            biopago_serial: biopago.serial,
            dejar_usd: dejar.usd,
            dejar_cop: dejar.cop,
            dejar_bs: dejar.bs,
            guardar_usd: caja.usd - dejar.usd,
            guardar_cop: caja.cop - dejar.cop,
            guardar_bs: caja.bs - dejar.bs,
            fecha: calcResult.fecha,
            total_punto_input: totalizarcierre && calcResult.cajeros_declarado ? calcResult.cajeros_declarado.caja_punto : lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0),
        }).then(res => {
            setLoading(false);
            if (res.data.estado) {
                alert("Guardado con éxito");
                if (onClose) onClose();
                else window.location.reload();
            } else {
                alert(res.data.msj);
            }
        }).catch(err => {
            setLoading(false);
            alert("Error al guardar");
        });
    };

    const handleReversar = () => {
        if (!window.confirm("¿Seguro que desea REVERSAR (ELIMINAR) el cierre de hoy? Esta acción no se puede deshacer.")) return;
        setLoading(true);
        db.reversarCierreV2({}).then(res => {
            setLoading(false);
            if (res.data.estado) {
                alert("Cierre reversado con éxito");
                if (onClose) onClose();
                else window.location.reload();
            } else {
                alert(res.data.msj);
            }
        });
    };

    if (loading) return (
        <div className="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded relative text-center">
            <h3 className="text-lg font-semibold">Procesando...</h3>
        </div>
    );

    // Admin View - Step 1 is skipped/hidden, only results
    if (totalizarcierre && step === 1) {
        return (
            <div className="bg-white rounded-lg shadow-md p-6 text-center">
                <h3 className="text-lg font-bold mb-4">Calculando Totales de Cierre Admin...</h3>
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-lg shadow-md">
            <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                <h5 className="text-lg font-bold mb-0">Nuevo Módulo de Cierre {totalizarcierre ? "(Admin)" : ""}</h5>
                <div className="flex gap-2">
                    {step === 1 && !totalizarcierre && (
                        <button 
                            className="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-sm" 
                            onClick={handleReversar}
                        >
                            Reversar Cierre
                        </button>
                    )}
                    {step === 2 && !totalizarcierre && (
                        <button 
                            className="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded text-sm" 
                            onClick={() => setStep(1)}
                        >
                            Corregir
                        </button>
                    )}
                </div>
            </div>
            <div className="p-6">
                {error && (
                    <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        {error}
                    </div>
                )}
                
                {step === 1 && (
                    <div className="animate-fade-in">
                        {/* Inputs Section */}
                        <div className="flex flex-wrap -mx-3 mb-6">
                            <div className="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                                <h6 className="text-green-600 font-bold mb-2 text-sm border-b border-green-200 pb-1">Efectivo (Billetes $)</h6>
                                <div className="grid grid-cols-2 gap-2">
                                    {Object.keys(billetes).map(denom => (
                                        <div key={denom} className="flex rounded-md shadow-sm h-8">
                                            <span className="inline-flex items-center justify-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-600 text-xs font-bold w-10 font-mono">
                                                {denom}
                                            </span>
                                            <input 
                                                type="number" 
                                                className="block w-full px-2 py-1 rounded-r-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-right text-sm"
                                                placeholder="0"
                                                value={billetes[denom]}
                                                onChange={e => setBilletes({...billetes, [denom]: e.target.value})}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="w-full md:w-1/3 px-3 mb-6 md:mb-0">
                                <h6 className="text-blue-600 font-bold mb-2 text-sm border-b border-blue-200 pb-1">Totales Caja</h6>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Total $
                                        </span>
                                        <CurrencyInput 
                                            className="text-green-600 font-bold text-xl"
                                            value={caja.usd} 
                                            onChange={val => setCaja(prev => ({...prev, usd: val}))} 
                                        />
                                    </div>
                                </div>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Total COP
                                        </span>
                                        <CurrencyInput 
                                            className="text-lg"
                                            value={caja.cop} 
                                            onChange={val => setCaja(prev => ({...prev, cop: val}))} 
                                        />
                                    </div>
                                </div>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Total Bs
                                        </span>
                                        <CurrencyInput 
                                            className="text-lg"
                                            value={caja.bs} 
                                            onChange={val => setCaja(prev => ({...prev, bs: val}))} 
                                        />
                                    </div>
                                </div>
                            </div>
                            <div className="w-full md:w-1/3 px-3">
                                <h6 className="text-gray-600 font-bold mb-2 text-sm border-b border-gray-200 pb-1">Dejar en Caja</h6>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Dejar $
                                        </span>
                                        <CurrencyInput 
                                            className="text-lg"
                                            value={dejar.usd} 
                                            onChange={val => setDejar(prev => ({...prev, usd: val}))} 
                                        />
                                    </div>
                                </div>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Dejar COP
                                        </span>
                                        <CurrencyInput 
                                            className="text-lg"
                                            value={dejar.cop} 
                                            onChange={val => setDejar(prev => ({...prev, cop: val}))} 
                                        />
                                    </div>
                                </div>
                                <div className="mb-2">
                                    <div className="flex rounded-md shadow-sm h-8">
                                        <span className="inline-flex items-center px-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-xs font-bold w-20">
                                            Dejar Bs
                                        </span>
                                        <CurrencyInput 
                                            className="text-lg"
                                            value={dejar.bs} 
                                            onChange={val => setDejar(prev => ({...prev, bs: val}))} 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr className="border-gray-200 my-6"/>
                        
                        {/* Lotes Section */}
                        <div className="flex flex-wrap -mx-3">
                            <div className="w-full md:w-7/12 px-3 mb-6 md:mb-0">
                                <h6 className="text-teal-600 font-bold mb-2">Puntos de Venta (Lotes)</h6>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 border border-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Banco</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Lote</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Monto</th>
                                                <th className="px-3 py-2 border-b"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {lotes.map((l, i) => (
                                                <tr key={i} className="hover:bg-gray-50">
                                                    <td className="px-3 py-2 text-sm text-gray-700">
                                                        {bancos.find(b => b.value == l.banco)?.text || l.banco}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 font-mono">{l.lote}</td>
                                                    <td className="px-3 py-2 text-sm text-gray-700 text-right font-mono">
                                                        {parseNum(l.monto).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        <button 
                                                            className="bg-red-500 hover:bg-red-600 text-white font-bold py-0 px-2 rounded text-xs" 
                                                            onClick={() => removeLote(i)}
                                                        >
                                                            x
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-gray-50">
                                                <td className="px-3 py-2">
                                                    <select 
                                                        className="block w-full pl-3 pr-10 py-1 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md"
                                                        value={newLote.banco} 
                                                        onChange={e => setNewLote({...newLote, banco: e.target.value})}
                                                    >
                                                        <option value="">Banco...</option>
                                                        {bancos.filter(b => b.value !== "0134").map(b => <option key={b.value} value={b.value}>{b.text}</option>)}
                                                    </select>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input 
                                                        type="text" 
                                                        className="block w-full px-2 py-1 text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 rounded-md font-mono"
                                                        placeholder="Lote" 
                                                        value={newLote.lote} 
                                                        onChange={e => setNewLote({...newLote, lote: e.target.value})} 
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <CurrencyInput 
                                                        className="text-sm"
                                                        placeholder="Monto" 
                                                        value={newLote.monto} 
                                                        onChange={val => setNewLote(prev => ({...prev, monto: val}))} 
                                                        onKeyDown={e => e.key === 'Enter' && handleAddLote()} 
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <button 
                                                        className="bg-green-500 hover:bg-green-600 text-white font-bold py-0 px-2 rounded text-xs" 
                                                        onClick={handleAddLote}
                                                    >
                                                        +
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr className="bg-gray-100 font-semibold">
                                                <td colSpan="2" className="px-3 py-2 text-right text-sm text-gray-700">Total Puntos:</td>
                                                <td className="px-3 py-2 text-right text-sm text-gray-700 font-mono">
                                                    {lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            
                            <div className="w-full md:w-5/12 px-3">
                                <h6 className="text-yellow-600 font-bold mb-2">Biopago</h6>
                                <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div className="mb-3">
                                        <div className="flex rounded-md shadow-sm">
                                            <span className="inline-flex items-center px-3 py-1 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500 text-sm">
                                                Serial
                                            </span>
                                            <input 
                                                type="text" 
                                                className="flex-1 block w-full px-3 py-1 rounded-r-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm font-mono"
                                                value={biopago.serial} 
                                                onChange={e => setBiopago({...biopago, serial: e.target.value})} 
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <div className="flex rounded-md shadow-sm">
                                            <span className="inline-flex items-center px-3 py-1 rounded-l-md border border-r-0 border-gray-300 bg-gray-100 text-gray-500 text-sm">
                                                Monto Bs
                                            </span>
                                            <CurrencyInput 
                                                className="text-lg"
                                                value={biopago.monto} 
                                                onChange={val => setBiopago(prev => ({...prev, monto: val}))} 
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8">
                            <button 
                                className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md flex items-center justify-center text-lg transition duration-150 ease-in-out" 
                                onClick={handleCalculate}
                            >
                                <i className="fa fa-calculator mr-2"></i> CALCULAR CIERRE
                            </button>
                        </div>
                    </div>
                )}

                {step === 2 && calcResult && (
                    <div className="animate-fade-in">
                        <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative text-center mb-6">
                            <h5 className="font-bold text-lg"><i className="fa fa-check-circle mr-2"></i> Cálculo Realizado</h5>
                            <p className="text-sm">Revise los montos antes de guardar</p>
                        </div>
                        
                        {totalizarcierre && calcResult.detalles_cajeros && (
                            <div className="mb-6 animate-fade-in">
                                <h6 className="text-indigo-600 font-bold mb-2 text-sm uppercase border-b border-indigo-200 pb-1">
                                    Detalle por Caja (Guardado)
                                </h6>
                                <div className="overflow-x-auto rounded-lg border border-gray-200">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-indigo-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cajero</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Efec $</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Punto Bs</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Biopago</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total $</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {calcResult.detalles_cajeros.map((c, i) => (
                                                <tr key={i} className="hover:bg-gray-50">
                                                    <td className="px-3 py-2 text-sm font-medium text-gray-900">
                                                        {c.usuario?.usuario || "Usuario " + c.id_usuario}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 text-right font-mono">
                                                        {parseNum(c.total_caja).toLocaleString()}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 text-right font-mono">
                                                        {parseNum(c.puntodeventa_actual_bs).toLocaleString()}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 text-right font-mono">
                                                        {parseNum(c.caja_biopago).toLocaleString()}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm font-bold text-gray-700 text-right font-mono">
                                                        {/* Assuming desc_total or similar is the total closed */}
                                                        {parseNum(c.desc_total).toLocaleString()}
                                                    </td>
                                                </tr>
                                            ))}
                                            {/* Totals Row */}
                                            <tr className="bg-indigo-100 font-bold">
                                                <td className="px-3 py-2 text-sm text-indigo-900">TOTALES</td>
                                                <td className="px-3 py-2 text-sm text-indigo-900 text-right font-mono">
                                                    {calcResult.cajeros_declarado?.caja_usd.toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-indigo-900 text-right font-mono">
                                                    {calcResult.cajeros_declarado?.caja_punto.toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-indigo-900 text-right font-mono">
                                                    {calcResult.cajeros_declarado?.caja_biopago.toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-indigo-900 text-right font-mono">
                                                    {/* Total of totals if needed */}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        <div className="flex flex-wrap -mx-3 mb-6 text-center">
                            <div className="w-full md:w-1/2 px-3 mb-4 md:mb-0">
                                <div className="bg-white rounded-lg shadow-sm h-full border-l-4 border-blue-500 p-6">
                                    <h6 className="text-gray-500 uppercase text-xs font-bold tracking-wider mb-2">Total Ventas</h6>
                                    <h2 className="text-4xl font-bold text-blue-600 mb-2 font-mono">
                                        {parseNum(calcResult.desc_total).toLocaleString(undefined, {minimumFractionDigits: 2})} $
                                    </h2>
                                    <span className="inline-block bg-gray-100 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-gray-300">
                                        {calcResult.numventas} Transacciones
                                    </span>
                                </div>
                            </div>
                             <div className="w-full md:w-1/2 px-3">
                                <div className="bg-white rounded-lg shadow-sm h-full border-l-4 border-green-500 p-6">
                                    <h6 className="text-gray-500 uppercase text-xs font-bold tracking-wider mb-2">Ganancia Estimada</h6>
                                    <h2 className="text-4xl font-bold text-green-600 mb-2 font-mono">
                                        {parseNum(calcResult.ganancia).toLocaleString(undefined, {minimumFractionDigits: 2})} $
                                    </h2>
                                    <span className="inline-block bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-green-300">
                                        {calcResult.porcentaje}% Rentabilidad
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto mb-6">
                            <table className="min-w-full divide-y divide-gray-200 border border-gray-200">
                                <thead className="bg-gray-800 text-white">
                                    <tr>
                                        <th className="px-4 py-3 text-center text-sm font-medium uppercase tracking-wider w-[30%]">Concepto</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium uppercase tracking-wider w-[25%]">Declarado (Usuario)</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium uppercase tracking-wider w-[25%]">Esperado (Sistema)</th>
                                        <th className="px-4 py-3 text-center text-sm font-medium uppercase tracking-wider w-[20%]">Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 font-mono text-sm">
                                    {/* Efectivo */}
                                    <tr className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-bold text-gray-700 font-sans">Efectivo ($)</td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {parseNum(caja.usd).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                        </td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {(parseNum(calcResult.caja_inicial) + parseNum(calcResult.sistema_efectivo)).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                            <div className="text-xs text-gray-500 italic mt-1 font-sans">
                                                (Ini: {parseNum(calcResult.caja_inicial).toFixed(2)})
                                            </div>
                                        </td>
                                        <td className={`px-4 py-3 text-center font-bold ${Math.abs(parseNum(caja.usd) - (parseNum(calcResult.caja_inicial) + parseNum(calcResult.sistema_efectivo))) > 5 ? "text-red-600" : "text-green-600"}`}>
                                            {(parseNum(caja.usd) - (parseNum(calcResult.caja_inicial) + parseNum(calcResult.sistema_efectivo))).toFixed(2)}
                                        </td>
                                    </tr>
                                    {/* Puntos */}
                                    <tr className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-bold text-gray-700 font-sans">Punto de Venta (Bs)</td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {totalizarcierre ? calcResult.cajeros_declarado?.caja_punto.toLocaleString() : lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                        </td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {parseNum(calcResult.sistema_punto).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                        </td>
                                        <td className={`px-4 py-3 text-center font-bold ${Math.abs((totalizarcierre ? calcResult.cajeros_declarado?.caja_punto : lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0)) - parseNum(calcResult.sistema_punto)) > 1 ? "text-red-600" : "text-green-600"}`}>
                                            {((totalizarcierre ? calcResult.cajeros_declarado?.caja_punto : lotes.reduce((acc, curr) => acc + parseNum(curr.monto), 0)) - parseNum(calcResult.sistema_punto)).toFixed(2)}
                                        </td>
                                    </tr>
                                    {/* Biopago */}
                                    <tr className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-bold text-gray-700 font-sans">Biopago (Bs)</td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {parseNum(biopago.monto).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                        </td>
                                        <td className="px-4 py-3 text-right text-gray-700">
                                            {parseNum(calcResult.sistema_biopago).toLocaleString(undefined, {minimumFractionDigits: 2})}
                                        </td>
                                        <td className={`px-4 py-3 text-center font-bold ${Math.abs(parseNum(biopago.monto) - parseNum(calcResult.sistema_biopago)) > 1 ? "text-red-600" : "text-green-600"}`}>
                                            {(parseNum(biopago.monto) - parseNum(calcResult.sistema_biopago)).toFixed(2)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        {parseNum(dejar.usd) > 0 && (
                            <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 flex items-start">
                                <i className="fa fa-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
                                <div className="text-yellow-700">
                                    <p className="font-bold">Atención:</p>
                                    <p>
                                        Se están dejando <strong>{parseNum(dejar.usd)} $</strong> en caja. 
                                        Se guardarán <strong>{(parseNum(caja.usd) - parseNum(dejar.usd)).toFixed(2)} $</strong>.
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex justify-between mt-6 pt-4 border-t border-gray-200">
                             <button 
                                className="bg-transparent hover:bg-gray-100 text-gray-700 font-semibold py-2 px-6 border border-gray-300 rounded shadow-sm flex items-center transition duration-150 ease-in-out" 
                                onClick={() => setStep(1)}
                             >
                                <i className="fa fa-arrow-left mr-2"></i> Corregir Datos
                             </button>
                             <button 
                                className="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-8 rounded shadow-md flex items-center transition duration-150 ease-in-out transform hover:scale-105" 
                                onClick={handleSave}
                             >
                                <i className="fa fa-save mr-2"></i> CONFIRMAR Y GUARDAR
                             </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
