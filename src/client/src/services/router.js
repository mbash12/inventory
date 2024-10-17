import * as VueRouter from "vue-router";
import NotFound from "../pages/NotFound.vue";
// import Home from "../pages/Home.vue";
import Login from "../pages/Login.vue";
import Otp from "../pages/Otp.vue";
import NewPassword from "../pages/NewPassword.vue";
import ForgotPassword from "../pages/ForgotPassword.vue";
import Profile from "../pages/Profile.vue";
import Detail from "../pages/Detail.vue";
import Delivery from "../pages/Delivery.vue";
import DeliveryDetail from "../pages/DeliveryDetail.vue";
import DeliveryInput from "../pages/DeliveryInput.vue";
import Inventory from "../pages/Inventory.vue";
import InventoryDetail from "../pages/InventoryDetail.vue";

import Page from "../pages/Page.vue";
import Projects from "../pages/Projects.vue";
import Project from "../pages/Project.vue";
import Deliveries from "../pages/Deliveries.vue";
import DeliveryReport from "../pages/DeliveryReport.vue";
import Inventories from "../pages/Inventories.vue";
import Warehouses from "../pages/Warehouses.vue";
import Warehouse from "../pages/Warehouse.vue";
import ShippingVendors from "../pages/ShippingVendors.vue";
import ShippingVendor from "../pages/ShippingVendor.vue";

import Clients from "../pages/Clients.vue";
import Client from "../pages/Client.vue";
import DeliveryLogs from "../pages/DeliveryLogs.vue";
import Finance from "../pages/Finance.vue";
import Followup from "../pages/Followup.vue";
import PoDeposits from "../pages/PoDeposits.vue";
import PoDeposit from "../pages/PoDeposit.vue";
import Threads from "../pages/Threads.vue";
import NotAuthorized from "../pages/NotAuthorized.vue";

import MktLogin from "../desktop/MktLogin.vue";
import MktForgotPassword from "../desktop/MktForgotPassword.vue";
import MktOtp from "../desktop/MktOtp.vue";
import MktNewPassword from "../desktop/MktNewPassword.vue";
import MktProject from "../desktop/MktProject.vue";
import MktPage from "../desktop/MktPage.vue";
import MktDelivery from "../desktop/MktDelivery.vue";
import MktDeliveryLog from "../desktop/MktDeliveryLog.vue";
import MktInventory from "../desktop/MktInventory.vue";
import MktNewProject from "../desktop/MktNewProject.vue";
import MktUser from "../desktop/MktUser.vue";
import MktNewUser from "../desktop/MktNewUser.vue";
import MktWarehouse from "../desktop/MktWarehouse.vue";
import MktNewWarehouse from "../desktop/MktNewWarehouse.vue";
import MktShippingVendor from "../desktop/MktShippingVendor.vue";
import MktNewShippingVendor from "../desktop/MktNewShippingVendor.vue";
import MktClient from "../desktop/MktClient.vue";
import MktNewClient from "../desktop/MktNewClient.vue";
import MktPoDeposit from "../desktop/MktPoDeposit.vue";
import MktNewPoDeposit from "../desktop/MktNewPoDeposit.vue";
import MktFollowup from "../desktop/MktFollowup.vue";
import MktFinance from "../desktop/MktFinance.vue";
import MktDashboard from "../desktop/MktDashboard.vue";
import MktDeadlines from "../desktop/MktDeadlines.vue";
import MktThreads from "../desktop/MktThreads.vue";
import MktNotAuthorized from "../desktop/MktNotAuthorized.vue";

import { reactive } from "vue";
import { checkLoggedin, isLoggedin } from "./service";
export const store = reactive({
    isLoading: false,
});
export const loading = (state = true) => {
    store.isLoading = state;
};

let routes = [
    {
        path: "/login",
        component: Login,
        name: "Login",
        meta: {
            requiresAuth: false,
        },
    },
    // {
    //     path: "/forgot-password",
    //     component: ForgotPassword,
    //     name: "ForgotPassword",
    //     meta: {
    //         requiresAuth: false,
    //     },
    // },
    // {
    //     path: "/verify-otp",
    //     component: Otp,
    //     name: "Otp",
    //     meta: {
    //         requiresAuth: false,
    //     },
    // },
    // {
    //     path: "/create-new-password",
    //     component: NewPassword,
    //     name: "NewPassword",
    //     meta: {
    //         requiresAuth: false,
    //     },
    // },
    {
        path: "/",
        redirect: { path: "/projects" },
        component: Page,
        children: [
            
            {
                path: "projects",
                component: Projects,
                name: "Projects",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance","delivery"],
                },
            },
            {
                path: "deliveries",
                component: Deliveries,
                name: "Deliveries",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "delivery-report",
                component: DeliveryReport,
                name: "Delivery Report",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "podeposits",
                component: PoDeposits,
                name: "PO Deposits",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance","delivery"],
                },
            },
            {
                path: "inventories",
                component: Inventories,
                name: "Inventories",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "clients",
                component: Clients,
                name: "Clients",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },
            {
                path: "warehouses",
                component: Warehouses,
                name: "Warehouses",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "shipping-vendors",
                component: ShippingVendors,
                name: "Shipping Vendors",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
        ],
    },
    
    {
        path: "/not-authorized",
        component: NotAuthorized,
        name: "Not Authorized",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    {
        path: "/not-authorized/:id",
        component: NotAuthorized,
        name: "Not Authorized 1",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    {
        path: "/projects/followup/:id",
        component: Followup,
        name: "Follow Up Project",
        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance"],
        },
    },
    {
        path: "/projects/finance/:id",
        component: Finance,
        name: "Finance Project",
        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance"],
        },
    },
    {
        path: "/threads/:id",
        component: Threads,
        name: "Threads",
        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance","delivery"],
        },
    },
    {
        path: "/delivery-report/logs/:id",
        component: DeliveryLogs,
        name: "Delivery Log",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    {
        path: "/shipping-vendors/add",
        component: ShippingVendor,
        name: "Add Shipping Vendor",

        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },

    {
        path: "/shipping-vendors/edit/:id",
        component: ShippingVendor,
        name: "Edit Shipping Vendor",

        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },

    {
        path: "/warehouses/add",
        component: Warehouse,
        name: "Add Warehouse",

        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },

    {
        path: "/warehouses/edit/:id",
        component: Warehouse,
        name: "Edit Warehouse",

        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/clients/add",
        component: Client,
        name: "Add Client",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing"],
        },
    },

    {
        path: "/clients/edit/:id",
        component: Client,
        name: "Edit Client",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing"],
        },
    },
    {
        path: "/projects/add",
        component: Project,
        name: "Add Project",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing"],
        },
    },
    {
        path: "/projects/edit/:id",
        component: Project,
        name: "Edit Project",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    {
        path: "/podeposits/add",
        component: PoDeposit,
        name: "Add PO Deposit",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing","finance"],
        },
    },
    {
        path: "/podeposits/edit/:id",
        component: PoDeposit,
        name: "Edit PO Deposit",

        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing","finance", "delivery"],
        },
    },
    // {
    //     path: "projects/add",
    //     component: NewProject,
    //     name: "Add Project",

    //     meta: {
    //         requiresAuth: true,
    // roles: ['admin','marketing','finance']
    //     },
    // },
    // {
    //     path: "/:page?",
    //     component: Home,
    //     name: "Home",
    //     meta: {
    //         requiresAuth: true,
    //         roles: ['admin','marketing','finance']
    //     },
    // },
    {
        path: "/details/:id",
        component: Detail,
        name: "Detail",
        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/delivery/:id",
        component: Delivery,
        name: "Delivery",
        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/delivery/details/:id",
        component: DeliveryDetail,
        name: "Delivery Details",
        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/delivery/add/:id",
        component: DeliveryInput,
        name: "Delivery Input",
        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/delivery/edit/:id",
        component: DeliveryInput,
        name: "Delivery Edit",
        meta: {
            requiresAuth: true,
            roles: ["admin", "delivery"],
        },
    },
    {
        path: "/inventory/:id",
        component: Inventory,
        name: "Inventory",
        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    {
        path: "/inventory/details/:id",
        component: InventoryDetail,
        name: "Inventory Details",
        meta: {
            requiresAuth: true,
            roles: ["admin", "marketing", "finance", "delivery"],
        },
    },
    // {
    //     path: "/profile",
    //     component: Profile,
    //     name: "Profile",
    //     meta: {
    //         requiresAuth: true,
    //     },
    // },
    {
        path: "/desktop/login",
        component: MktLogin,
        name: "Desktop Login",
        meta: {
            requiresAuth: false,
        },
    },
    // {
    //     path: "/desktop/forgot-password",
    //     component: MktForgotPassword,
    //     name: "Desktop Forgot Password",
    //     meta: {
    //         requiresAuth: true,
    //     },
    // },
    // {
    //     path: "/desktop/verify-otp",
    //     component: MktOtp,
    //     name: "Desktop Verify OTP",
    //     meta: {
    //         requiresAuth: true,
    //     },
    // },
    // {
    //     path: "/desktop/create-new-password",
    //     component: MktNewPassword,
    //     name: "Desktop Create New Password",
    //     meta: {
    //         requiresAuth: true,
    //     },
    // },
    
    {
        path: "/desktop",
        redirect: { path: "/desktop/projects" },
        component: MktPage,
        children: [
            {
                path: "dashboard",
                component: MktDashboard,
                name: "Desktop Dashboard",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "finance"],
                },
            },
            {
                path: "not-authorized",
                component: MktNotAuthorized,
                name: "Desktop Not Authorized",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "not-authorized/:id",
                component: MktNotAuthorized,
                name: "Desktop Not Authorized 1",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "projects",
                component: MktProject,
                name: "Desktop Project",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "projects/add",
                component: MktNewProject,
                name: "Desktop Add Project",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },
            {
                path: "projects/edit/:id",
                component: MktNewProject,
                name: "Desktop Edit Project",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing","finance","delivery"],
                },
            },
            {
                path: "delivery-report",
                component: MktDelivery,
                name: "Desktop Delivery",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "delivery-report/logs/:id",
                component: MktDeliveryLog,
                name: "Desktop Delivery Log",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "inventory",
                component: MktInventory,
                name: "Desktop Inventory",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },

            {
                path: "users",
                component: MktUser,
                name: "Desktop Manage Users",

                meta: {
                    requiresAuth: true,
                    roles: ["admin"],
                },
            },
            {
                path: "users/add",
                component: MktNewUser,
                name: "Desktop Add User",

                meta: {
                    requiresAuth: true,
                    roles: ["admin"],
                },
            },
            {
                path: "users/edit/:id",
                component: MktNewUser,
                name: "Desktop Edit User",

                meta: {
                    requiresAuth: true,
                    roles: ["admin"],
                },
            },

            {
                path: "warehouses",
                component: MktWarehouse,
                name: "Desktop Manage Warehouses",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "warehouses/add",
                component: MktNewWarehouse,
                name: "Desktop Add Warehouse",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "warehouses/edit/:id",
                component: MktNewWarehouse,
                name: "Desktop Edit Warehouse",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },

            {
                path: "shipping-vendors",
                component: MktShippingVendor,
                name: "Desktop Manage Shipping Vendors",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "shipping-vendors/add",
                component: MktNewShippingVendor,
                name: "Desktop Add Shipping Vendor",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "shipping-vendors/edit/:id",
                component: MktNewShippingVendor,
                name: "Desktop Edit Shipping Vendors",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "delivery"],
                },
            },
            {
                path: "clients",
                component: MktClient,
                name: "Desktop Manage Clients",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },
            {
                path: "clients/add",
                component: MktNewClient,
                name: "Desktop Add Client",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },
            {
                path: "clients/edit/:id",
                component: MktNewClient,
                name: "Desktop Edit Client",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },

            {
                path: "podeposits",
                component: MktPoDeposit,
                name: "Desktop PO Deposit",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "podeposits/add",
                component: MktNewPoDeposit,
                name: "Desktop Add PO Deposit",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing"],
                },
            },
            {
                path: "podeposits/edit/:id",
                component: MktNewPoDeposit,
                name: "Desktop Edit PO Deposit",

                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing","finance","delivery"],
                },
            },
            {
                path: "projects/followup/:id",
                component: MktFollowup,
                name: "Desktop Follow Up Project",
                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance"],
                },
            },
            {
                path: "projects/finance/:id",
                component: MktFinance,
                name: "Desktop Finance Project",
                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance"],
                },
            },
            {
                path: "deadlines",
                component: MktDeadlines,
                name: "Desktop Deadlines",
                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
            {
                path: "threads/:id",
                component: MktThreads,
                name: "Desktop Threads",
                meta: {
                    requiresAuth: true,
                    roles: ["admin", "marketing", "finance", "delivery"],
                },
            },
        ],
    },

    {
        path: "/:any",
        component: NotFound,
        name: "NotFound",
    },
    {
        path: "/desktop/:any",
        component: NotFound,
        name: "NotFound",
    },
];

const router = VueRouter.createRouter({
    history: VueRouter.createWebHashHistory(),
    routes,
});
router.beforeEach(async (to, from, next) => {
    let logged = await checkLoggedin();
    if (to.matched.some((record) => record.meta.requiresAuth)) {
        if (!logged) {
            next({
                name: to.path.includes("desktop") ? "Desktop Login" : "Login",
            });
        } else {
            if (to.meta.roles.includes(logged.user.position)) {
                next();
            } else {
                next({
                    name:
                        logged.user.position == "delivery"
                            ? to.path.includes("desktop")
                                ? "Desktop Delivery"
                                : "Deliveries"
                            : to.path.includes("desktop")
                            ? "Desktop Projects"
                            : "Projects",
                });
            }
        }
    } else {
        next();
    }
});
export default router;
