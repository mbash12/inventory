import { apilist } from "./apilist";
import { reactive, ref } from "vue";

// Constants for accounting integration - Easy to update
const DEFAULT_COMPANY_ID = 12; // Change this to your default company ID

// export const URLL = "";
// export const URLL = "http://10.10.1.84:8000";
export const URLL = "http://localhost:8000";
// export const URLL = "https://inventory.dotcomsolution.co.id";
export const APIURL = URLL + "/api";
export const ASSETSURL = URLL + "/storage/";
export const isLoggedin = ref(false);
export const currentUser = reactive({ user: null });
export const data = reactive({ notifications: 0 });
export const nom = (num = "0", decimals = 2) => {
    if (num === null || num === undefined) {
        num = "0";
    }
    
    // Convert to number first
    let numericValue = parseFloat(num);
    if (isNaN(numericValue)) {
        numericValue = 0;
    }
    
    // Format with fixed decimal places
    const fixedValue = numericValue.toFixed(decimals);
    
    // Split into integer and decimal parts
    const [intPart, decPart] = fixedValue.split('.');
    
    // Add thousand separators to integer part
    const formattedInt = intPart.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    
    // Return with comma as decimal separator (Indonesian format)
    return decPart === undefined ? formattedInt : `${formattedInt},${decPart}`;
}
import router from "./router"
export const goto = (link) => {
    return router.push(link)
}
export const goback = () => {
    return router.back()
}
export const api = async (key, options = {}) => {
    const {method = 'GET', auth = false, url} = apilist[key];
    const {route = "", body, params, file} = options;
    
    let headers = { Accept: 'application/json' };
    let formData;

    if (file) {
        formData = new FormData();
        formData.append('file', file);
        if (body) {
            Object.keys(body).forEach(key => {
                formData.append(key, body[key]);
            });
        }
    } else {
        headers["Content-Type"] = "application/json";
    }

    if (auth) {
        headers["Authorization"] = `Bearer ${currentUser.user.token}`;
    }

    try {
        const response = await fetch(`${APIURL}${url}${route}${params ? "?" + new URLSearchParams(params).toString() : ""}`, {
            method,
            headers,
            body: file ? formData : (method !== "GET" && body ? JSON.stringify(body) : undefined)
        });

        const result = await response.json();
        if (result.code === 401) { logout(); location.reload(); return; }
        return result;
    } catch (error) {
        throw error;
    }
};
export const checkLoggedin = (force = false) => {
    return new Promise((resolve, reject) => {
        if (!force) if (currentUser.user) resolve(currentUser.user);
        let x = localStorage.getItem("X917u");
        if (x) {
            currentUser.user = JSON.parse(atob(x));
            resolve(currentUser.user);
        } else {
            resolve(null);
        }
    });
};
export const login = async (email, password) =>  {
    let result = await api("login", { body: { email, password } });
    if (result.code === 200) {
        currentUser.user = result.data;
        localStorage.setItem("X917u", btoa(JSON.stringify(result.data)));
    }
    return result;
};
export const forgot = async (email) =>  await api("forgot", { body: { email } });
export const checkOtp = async (otp_code) => {
    let email = localStorage.getItem("email");
    let result = await api("verifyOtp", { body: { email, otp_code } });
    return result;
};
export const changePassword = async (password, password_confirmation) => {
    let email = localStorage.getItem("email");
    let otp_code = localStorage.getItem("otp_code");
    let result = await api("resetPassword", {
        body: { email, otp_code, password, password_confirmation },
    });
    return result;
};
export const upload = async (data) => await api("upload", { file: data });
export const logout = () => {
    localStorage.clear();
};
// export const checkOtp = null;
export const updateProfile = null;
// export const changePassword = null;
export const getNotificationList = async (filter) => await api("getNotificationList", { params: filter });
export const readNotification = async (id) => await api("readNotification", { route: "/" + id });
export const getNotificationCount = async () => await api("getNotificationCount");
export const setToken = async (data) => await api("setToken", {body: data});
export const getWarhouseList = async (filters) => await api("getWarhouseList", { params: filters });
export const getInventoryList = async (filters) => await api("getInventoryList", { params: filters });
export const getInventoriess = async (id) => await api("getInventoriess", { route: "/" + id });
export const stockError = (result) => Object.values(result?.errors ?? {}).flat().join(' ') || result?.message || 'Transaksi gagal. Silakan coba lagi.';
const stockApi = async (key, options = {}) => {
    const result = await api(key, options);
    if (result?.code !== 200) throw new Error(stockError(result));
    return result;
};
export const getStockInOptions = (params) => stockApi('stockInOptions', { params });
export const getStockIns = (params) => stockApi('stockIns', { params });
export const getStockIn = (id) => stockApi('stockIns', { route: '/' + id });
export const saveStockIn = (id, body) => stockApi(id ? 'updateStockIn' : 'createStockIn', { route: id ? '/' + id : '', body });
export const deleteStockIn = (id) => stockApi('deleteStockIn', { route: '/' + id });
export const getManualItems = (params) => stockApi('manualItems', { params });
export const saveManualItem = (id, body) => stockApi(id ? 'updateManualItem' : 'createManualItem', { route: id ? '/' + id : '', body });
export const getStockCards = (params) => stockApi('stockCard', { params });
export const getStockCardDetail = (params) => stockApi('stockCardDetail', { params });
export const getClientList = async (filters) => await api("getClientList", { params: filters });
export const getClient = async (id) => await api("getClient", { route: "/" + id });
export const createClient = async (data) => await api("createClient", { body: data });
export const updateClient = async (id, data) => await api("updateClient", { route: "/" + id, body: data });
export const deleteClient = async (id) => await api("deleteClient", { route: "/" + id });
export const getProjectList = async (filters) => await api("getProjectList", { params: filters });
export const getProjectLists = async (filters) => await api("finance", { params: filters });
export const getProject = async (id) => await api("getProject", { route: "/" + id });
export const createProject = async (data) => await api("createProject", { body: data });
export const updateProject = async (id, data) => await api("updateProject", { route: "/" + id, body: data });
export const deleteProject = async (id) => await api("deleteProject", { route: "/" + id });
export const getPodepositList = async (filters) => await api("getPodepositList", { params: filters });
export const getPodeposit = async (id) => await api("getPodeposit", { route: "/" + id });
export const createPodeposit = async (data) => await api("createPodeposit", { body: data });
export const updatePodeposit = async (id, data) => await api("updatePodeposit", { route: "/" + id, body: data });
export const deletePodeposit = async (id) => await api("deletePodeposit", { route: "/" + id });
export const getProjectTodo = async (filters) => await api("getProjectTodo", { params: filters });
export const cancelPodeposit = async (id) => await api("updatePodeposit", { route: "/" + id, body: JSON.stringify({
    status: "cancel"
}) });

export const projectDelivery = async (id, data) => await api("projectDelivery", { route: "/" + id, body: data });
export const getSnippet = async (id, data) => await api("getSnippet", { route: "/" + id, body: data });
export const getUserList = async (filters) => await api("getUserList", { params: filters });
export const getUser = async (id) => await api("getUser", { route: "/" + id });
export const createUser = async (data) => await api("createUser", { body: data });
export const updateUser = async (id, data) => await api("updateUser", { route: "/" + id, body: data });
export const deleteUser = async (id) => await api("deleteUser", { route: "/" + id });
export const getWarehouseList = async (filters) => await api("getWarehouseList", { params: filters });
export const getWarehouse = async (id) => await api("getWarehouse", { route: "/" + id });
export const createWarehouse = async (data) => await api("createWarehouse", { body: data });
export const updateWarehouse = async (id, data) => await api("updateWarehouse", { route: "/" + id, body: data });
export const deleteWarehouse = async (id) => await api("deleteWarehouse", { route: "/" + id });
export const getShippingVendorList = async (filters) => await api("getShippingVendorList", { params: filters });
export const getShippingVendor = async (id) => await api("getShippingVendor", { route: "/" + id });
export const createShippingVendor = async (data) => await api("createShippingVendor", { body: data });
export const updateShippingVendor = async (id, data) => await api("updateShippingVendor", {route: "/" + id,body: data});
export const deleteShippingVendor = async (id) => await api("deleteShippingVendor", { route: "/" + id });
export const getDeliveryList = async (id) => await api("getDeliveryList", { route: "/" + id });
export const getDelivery = async (id) => await api("getDelivery", { route: "/" + id });
export const createDelivery = async (data) => await api("createDelivery", { body: data });
export const updateDelivery = async (id, data) => await api("updateDelivery", { route: "/" + id, body: data });
export const deleteDelivery = async (id, data) => await api("deleteDelivery", { route: "/" + id, body: data });
export const getLogsList = async (id) => await api("getLogsList", { route: "/" + id });
export const getLogList = async (id) => await api("getLogList", { route: "/" + id });
export const getLog = async (id) => await api("getLog", { route: "/" + id });
export const getMisc = async (id) => await api("getMisc", { route: "/" + id });
export const getProgress = async (id) => await api("getProgress", { route: "/" + id });
export const createProgress = async (id, data) => await api("createProgress", { route: "/" + id, body: data });
export const getThreadsCount = async () => await api("getThreadsCount");
export const getThreads = async (id) => await api("getThreads", { route: "/" + id });
export const createThreads = async (id, data) => await api("createThreads", { body: data });
export const getFollowup = async (id) => await api("getFollowup", { route: "/" + id });
export const createFollowup = async (id, data) => await api("createFollowup", { route: "/" + id, body: data });
export const updateFollowup = async (id, data) => await api("updateFollowup", { route: "/" + id, body: data });
export const getReport = async (year) => await api("getReport", { params: {year:year} });

// API functions for getting UOM and Tax data from accounting app
// Using proxy through inventory app to handle authentication with accounting app

export const getUomList = async (ppnType = 'ppn') => {
    const headers = {
        "Content-Type": "application/json",
    };

    // Add authorization header if user is logged in (same as other API calls)
    if (currentUser.user && currentUser.user.token) {
        headers["Authorization"] = `Bearer ${currentUser.user.token}`;
    }

    const response = await fetch(`${APIURL}/proxy/accounting/unit?ppn_type=${ppnType}`, {
        method: 'GET',
        headers: headers
    });
    return response.json();
};

export const getTaxList = async (ppnType = 'ppn') => {
    const headers = {
        "Content-Type": "application/json",
    };

    // Add authorization header if user is logged in (same as other API calls)
    if (currentUser.user && currentUser.user.token) {
        headers["Authorization"] = `Bearer ${currentUser.user.token}`;
    }

    const response = await fetch(`${APIURL}/proxy/accounting/taxes?ppn_type=${ppnType}`, {
        method: 'GET',
        headers: headers
    });
    return response.json();
};

// API function for getting Product list from accounting app
export const getProductList = async (ppnType = 'ppn') => {
    const headers = {
        "Content-Type": "application/json",
    };

    // Add authorization header if user is logged in (same as other API calls)
    if (currentUser.user && currentUser.user.token) {
        headers["Authorization"] = `Bearer ${currentUser.user.token}`;
    }

    const response = await fetch(`${APIURL}/proxy/accounting/products?ppn_type=${ppnType}`, {
        method: 'GET',
        headers: headers
    });
    return response.json();
};

// API function for getting Customer/Contact list from accounting app
export const getCustomerList = async (search = null, ppnType = 'ppn') => {
    const headers = {
        "Content-Type": "application/json",
    };

    // Add authorization header if user is logged in (same as other API calls)
    if (currentUser.user && currentUser.user.token) {
        headers["Authorization"] = `Bearer ${currentUser.user.token}`;
    }

    let url = `${APIURL}/proxy/accounting/customers?ppn_type=${ppnType}`;
    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
    }

    const response = await fetch(url, {
        method: 'GET',
        headers: headers
    });
    return response.json();
};


export const sortByProperty = (arr, property, direction = 'asc') =>
[...arr].sort((a, b) => {
const compareValue = a[property] - b[property];
return direction === 'asc' ? compareValue : -compareValue;
});

// Sync API functions
export const getSyncJobs = async (filters) => await api("getSyncJobs", { params: filters });
export const getSyncJob = async (id) => await api("getSyncJob", { route: "/" + id });
export const retrySyncJob = async (id) => await api("retrySyncJob", { route: "/" + id + "/retry" });
export const syncPoDeposit = async (data) => await api("syncPoDeposit", { body: data });
export const getSyncPoDeposits = async (filters) => await api("getSyncPoDeposits", { params: filters });
export const getSyncPoDepositStatus = async (id) => await api("getSyncPoDepositStatus", { route: "/" + id });
export const retrySyncPoDeposit = async (id) => await api("retrySyncPoDeposit", { route: "/" + id + "/retry" });
export const clearSyncData = async (data) => await api("clearSyncData", { body: data });
export const getAvailablePoDeposits = async (filters) => await api("getAvailablePoDeposits", { params: filters });