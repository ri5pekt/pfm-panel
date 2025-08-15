// src/constants/countryOptions.js
// Woo-compatible ISO-3166 alpha-2 codes everywhere.

// --- Popular ---
export const POPULAR_COUNTRIES = [
    { label: "United States", value: "US" },
    { label: "United Kingdom", value: "GB" },
    { label: "Canada", value: "CA" },
    { label: "Australia", value: "AU" },
    { label: "Israel", value: "IL" },
];

// --- EU (object form, consistent with the others) ---
export const EU_COUNTRIES = [
    ["AT", "Austria"],
    ["BE", "Belgium"],
    ["BG", "Bulgaria"],
    ["HR", "Croatia"],
    ["CY", "Cyprus"],
    ["CZ", "Czechia"],
    ["DK", "Denmark"],
    ["EE", "Estonia"],
    ["FI", "Finland"],
    ["FR", "France"],
    ["DE", "Germany"],
    ["GR", "Greece"],
    ["HU", "Hungary"],
    ["IE", "Ireland"],
    ["IT", "Italy"],
    ["LV", "Latvia"],
    ["LT", "Lithuania"],
    ["LU", "Luxembourg"],
    ["MT", "Malta"],
    ["NL", "Netherlands"],
    ["PL", "Poland"],
    ["PT", "Portugal"],
    ["RO", "Romania"],
    ["SK", "Slovakia"],
    ["SI", "Slovenia"],
    ["ES", "Spain"],
    ["SE", "Sweden"],
].map(([value, label]) => ({ label, value }));

// --- Full list from your CSV (Unknown excluded). We'll split into groups below. ---
const ALL_FROM_FILE = [
    { label: "Aland Islands", value: "AX" },
    { label: "Albania", value: "AL" },
    { label: "Algeria", value: "DZ" },
    { label: "American Samoa", value: "AS" },
    { label: "Andorra", value: "AD" },
    { label: "Anguilla", value: "AI" },
    { label: "Antigua And Barbuda", value: "AG" },
    { label: "Argentina", value: "AR" },
    { label: "Australia", value: "AU" },
    { label: "Austria", value: "AT" },
    { label: "Bahamas", value: "BS" },
    { label: "Bahrain", value: "BH" },
    { label: "Barbados", value: "BB" },
    { label: "Belarus", value: "BY" },
    { label: "Belgium", value: "BE" },
    { label: "Belize", value: "BZ" },
    { label: "Bermuda", value: "BM" },
    { label: "Bolivia", value: "BO" },
    { label: "Bolivia, Plurinational State Of", value: "BO" },
    { label: "Bosnia And Herzegovina", value: "BA" },
    { label: "Botswana", value: "BW" },
    { label: "Brazil", value: "BR" },
    { label: "Brunei Darussalam", value: "BN" },
    { label: "Bulgaria", value: "BG" },
    { label: "Cambodia", value: "KH" },
    { label: "Cameroon", value: "CM" },
    { label: "Canada", value: "CA" },
    { label: "Cayman Islands", value: "KY" },
    { label: "Chile", value: "CL" },
    { label: "China", value: "CN" },
    { label: "Colombia", value: "CO" },
    { label: "Congo", value: "CG" },
    { label: "Congo, The Democratic Republic Of The", value: "CD" },
    { label: "Costa Rica", value: "CR" },
    { label: "Cote D'Ivoire", value: "CI" },
    { label: "Croatia", value: "HR" },
    { label: "Cyprus", value: "CY" },
    { label: "Czechia", value: "CZ" },
    { label: "Czech Republic", value: "CZ" },
    { label: "Denmark", value: "DK" },
    { label: "Dominica", value: "DM" },
    { label: "Dominican Republic", value: "DO" },
    { label: "Ecuador", value: "EC" },
    { label: "Egypt", value: "EG" },
    { label: "El Salvador", value: "SV" },
    { label: "Estonia", value: "EE" },
    { label: "Ethiopia", value: "ET" },
    { label: "Faroe Islands", value: "FO" },
    { label: "Finland", value: "FI" },
    { label: "France", value: "FR" },
    { label: "Georgia", value: "GE" },
    { label: "Germany", value: "DE" },
    { label: "Ghana", value: "GH" },
    { label: "Gibraltar", value: "GI" },
    { label: "Greece", value: "GR" },
    { label: "Greenland", value: "GL" },
    { label: "Grenada", value: "GD" },
    { label: "Guam", value: "GU" },
    { label: "Guatemala", value: "GT" },
    { label: "Guernsey", value: "GG" },
    { label: "Honduras", value: "HN" },
    { label: "Hong Kong", value: "HK" },
    { label: "Hungary", value: "HU" },
    { label: "Iceland", value: "IS" },
    { label: "India", value: "IN" },
    { label: "Indonesia", value: "ID" },
    { label: "Ireland", value: "IE" },
    { label: "Isle Of Man", value: "IM" },
    { label: "Israel", value: "IL" },
    { label: "Italy", value: "IT" },
    { label: "Jamaica", value: "JM" },
    { label: "Japan", value: "JP" },
    { label: "Jersey", value: "JE" },
    { label: "Jordan", value: "JO" },
    { label: "Kazakhstan", value: "KZ" },
    { label: "Kenya", value: "KE" },
    { label: "Korea", value: "KR" }, // South Korea
    { label: "Kuwait", value: "KW" },
    { label: "Kyrgyzstan", value: "KG" },
    { label: "Laos", value: "LA" },
    { label: "Latvia", value: "LV" },
    { label: "Lebanon", value: "LB" },
    { label: "Liechtenstein", value: "LI" },
    { label: "Lithuania", value: "LT" },
    { label: "Luxembourg", value: "LU" },
    { label: "Macao", value: "MO" },
    { label: "Macedonia", value: "MK" },
    { label: "Madagascar", value: "MG" },
    { label: "Malawi", value: "MW" },
    { label: "Malaysia", value: "MY" },
    { label: "Maldives", value: "MV" },
    { label: "Malta", value: "MT" },
    { label: "Martinique", value: "MQ" },
    { label: "Mauritius", value: "MU" },
    { label: "Mexico", value: "MX" },
    { label: "Micronesia, Federated States Of", value: "FM" },
    { label: "Moldova", value: "MD" },
    { label: "Moldova, Republic Of", value: "MD" },
    { label: "Monaco", value: "MC" },
    { label: "Mongolia", value: "MN" },
    { label: "Montenegro", value: "ME" },
    { label: "Morocco", value: "MA" },
    { label: "Myanmar", value: "MM" },
    { label: "Namibia", value: "NA" },
    { label: "Nepal", value: "NP" },
    { label: "Netherlands", value: "NL" },
    { label: "New Zealand", value: "NZ" },
    { label: "Nicaragua", value: "NI" },
    { label: "Nigeria", value: "NG" },
    { label: "Northern Mariana Islands", value: "MP" },
    { label: "Norway", value: "NO" },
    { label: "Oman", value: "OM" },
    { label: "Pakistan", value: "PK" },
    { label: "Palestinian Territory, Occupied", value: "PS" },
    { label: "Panama", value: "PA" },
    { label: "Paraguay", value: "PY" },
    { label: "Peru", value: "PE" },
    { label: "Philippines", value: "PH" },
    { label: "Poland", value: "PL" },
    { label: "Portugal", value: "PT" },
    { label: "Puerto Rico", value: "PR" },
    { label: "Qatar", value: "QA" },
    { label: "Reunion", value: "RE" },
    { label: "Romania", value: "RO" },
    { label: "Russian Federation", value: "RU" },
    { label: "Rwanda", value: "RW" },
    { label: "Saint Kitts And Nevis", value: "KN" },
    { label: "Saint Lucia", value: "LC" },
    { label: "Saint Vincent And Grenadines", value: "VC" },
    { label: "Saudi Arabia", value: "SA" },
    { label: "Senegal", value: "SN" },
    { label: "Serbia", value: "RS" },
    { label: "Seychelles", value: "SC" },
    { label: "Sierra Leone", value: "SL" },
    { label: "Singapore", value: "SG" },
    { label: "Slovakia", value: "SK" },
    { label: "Slovenia", value: "SI" },
    { label: "South Africa", value: "ZA" },
    { label: "Spain", value: "ES" },
    { label: "Sri Lanka", value: "LK" },
    { label: "Suriname", value: "SR" },
    { label: "Swaziland", value: "SZ" },
    { label: "Sweden", value: "SE" },
    { label: "Switzerland", value: "CH" },
    { label: "Taiwan", value: "TW" },
    { label: "Tanzania, United Republic Of", value: "TZ" },
    { label: "Thailand", value: "TH" },
    { label: "Timor-Leste", value: "TL" },
    { label: "Trinidad And Tobago", value: "TT" },
    { label: "Tunisia", value: "TN" },
    { label: "Turkey", value: "TR" },
    { label: "Uganda", value: "UG" },
    { label: "Ukraine", value: "UA" },
    { label: "United Arab Emirates", value: "AE" },
    { label: "United Kingdom", value: "GB" },
    { label: "United States", value: "US" },
    { label: "United States Outlying Islands", value: "UM" },
    { label: "Uruguay", value: "UY" },
    { label: "Uzbekistan", value: "UZ" },
    { label: "Venezuela, Bolivarian Republic Of", value: "VE" },
    { label: "Viet Nam", value: "VN" },
    { label: "Virgin Islands, U.S.", value: "VI" },
    { label: "Zambia", value: "ZM" },
];

// --- Derive REST_OF_WORLD = ALL - EU - POPULAR ---
const EU_SET = new Set(EU_COUNTRIES.map((c) => c.value));
const POP_SET = new Set(POPULAR_COUNTRIES.map((c) => c.value));

export const REST_OF_WORLD = ALL_FROM_FILE.filter((c) => !EU_SET.has(c.value) && !POP_SET.has(c.value))
    // de-dup labels that mapped to same code (e.g., "Czechia" and "Czech Republic" → CZ)
    .filter(
        ((c) => {
            const seen = new Set();
            return (x) => (seen.has(x.value) ? false : (seen.add(x.value), true));
        })()
    );

// --- Grouped options for <n-select> ---
export const countryOptions = [
    { type: "group", label: "Popular", key: "popular", children: POPULAR_COUNTRIES },
    { type: "group", label: "European Union", key: "eu", children: EU_COUNTRIES },
    { type: "group", label: "Rest of the world", key: "rest", children: REST_OF_WORLD },
];

// ---------------------------
// Helper functions (vanilla)
// ---------------------------
export function buildCountryLabelMap(options = countryOptions) {
    const map = {};
    for (const item of options) {
        if (item?.type === "group" && Array.isArray(item.children)) {
            for (const c of item.children) map[c.value] = c.label;
        } else if (item?.value) {
            map[item.value] = item.label;
        }
    }
    return map;
}

export function countryName(code, options = countryOptions) {
    if (!code) return "";
    const map = buildCountryLabelMap(options);
    return map[code] || code;
}

// If an incoming value is a name (e.g., "United States"), coerce to code when possible.
export function coerceCountryToCode(v, options = countryOptions) {
    if (!v) return "";
    const vv = String(v).trim();
    const map = buildCountryLabelMap(options);
    // already a known code?
    if (map[vv]) return vv;
    // match by label (case-insensitive)
    const entry = Object.entries(map).find(([, label]) => label.toLowerCase() === vv.toLowerCase());
    return entry ? entry[0] : vv.toUpperCase();
}

// ---------------------------
// Vue-friendly computed hook
// ---------------------------
// usage (in component):
//   const countryLabelMap = makeCountryLabelComputed(computed);
//   countryLabelMap.value["US"] -> "United States"
export function makeCountryLabelComputed(computedRef, optionsRef) {
    return computedRef(() => buildCountryLabelMap(optionsRef?.value ?? countryOptions));
}
