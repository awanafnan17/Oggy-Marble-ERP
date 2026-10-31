export const formatPKR = (amount: number | null | undefined): string => {
    if (amount == null) return 'Rs. 0';
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
        minimumFractionDigits: 0
    }).format(amount);
};

export const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return d.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }).replace(/\//g, '-');
};
