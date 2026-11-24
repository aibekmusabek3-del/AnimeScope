export const genres = [[1,'Экшен'],[2,'Приключения'],[4,'Комедия'],[8,'Драма'],[10,'Фэнтези'],[7,'Детектив'],[14,'Ужасы'],[22,'Романтика'],[24,'Фантастика'],[36,'Повседневность'],[37,'Сверхъестественное'],[41,'Триллер'],[30,'Спорт']];
export const nav = [
    ['top','compass','Обзор'], ['popular','flame','Популярное'], ['season','calendar','Сейчас в эфире'],
    ['upcoming','clock','Скоро'], ['catalog','grid','Весь каталог'], ['favorites','bookmark','Моя коллекция'],
];
export const typeLabels = {TV:'Сериал', Movie:'Фильм', OVA:'OVA', ONA:'ONA', Special:'Спешл'};
export const statusLabels = {finished:'Завершён',current:'Выходит',upcoming:'Анонсирован',unreleased:'Не вышел',tba:'Дата не объявлена'};
export const defaultFilters = {q:'', genre:'', type:'', sort:'score', page:1, mode:'top'};
export const reasons = {
    certificate:'Не удалось проверить TLS-сертификат. Проверьте настройки PHP.',
    dns:'Сервер Shikimori не найден через DNS.', timeout:'Сервер Shikimori не ответил вовремя.',
    rate_limit:'Shikimori временно ограничил число запросов.', upstream:'Внешний сервис временно недоступен.',
    connection:'Не удалось соединиться с Shikimori.', invalid_response:'Shikimori вернул неожиданный формат данных.',
    http_error:'Shikimori отклонил запрос.', not_found:'Данные не найдены во внешнем сервисе.',
};
export function titleOf(item) { return item.russian || item.english || item.title || 'Без названия'; }
export function scoreOf(item) { return typeof item.score === 'number' && item.score > 0 ? item.score.toFixed(1) : null; }
export function listUrl(filters, refresh = false) {
    const params = new URLSearchParams();
    for (const [key,value] of Object.entries(filters)) if (value !== '' && value !== null && value !== undefined) params.set(key,String(value));
    if (refresh) params.set('refresh','1');
    return `/api/anime?${params}`;
}
export function toggleFavorite(items, item) { return items.some(a=>a.id===item.id) ? items.filter(a=>a.id!==item.id) : [item,...items]; }
export function loadFavorites(storage) {
    try { const items = JSON.parse(storage.getItem('animescope.favorites.shikimori.v1') || '[]'); return Array.isArray(items) ? items.filter(a=>a && Number.isInteger(a.id)) : []; } catch { return []; }
}
export function readDetail(hash) { const match = /^#\/anime\/(\d+)$/.exec(hash); return match && Number(match[1]) > 0 ? Number(match[1]) : null; }

// Compare IDs independently of the order returned by the external API.
export function pageSignature(items) { return [...new Set((items || []).map(a => String(a.id)))].sort().join(','); }
