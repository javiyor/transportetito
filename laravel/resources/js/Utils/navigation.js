export const parentPath = () => {
    if (typeof window === 'undefined') return '/'

    const segments = window.location.pathname.split('/').filter(Boolean)
    segments.pop()

    return segments.length ? `/${segments.join('/')}` : '/'
}
