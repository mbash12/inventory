import colors from "windicss/colors";

export default {
  theme: {
    extend: {
      colors: {
        app: {
          100: "#12BD59", // status new
          200: "#07B150", // outline
          300: "#ff8080", // bg1
          400: "#ff4d4d", // bg2
          500: "#ff1a1a", // active tab
          600: "#e60000", // button

          700: "#EBC11D", // yellow
          800: "#FF0000", // red
          900: "#3786FB", // blue
          // 110: "#09A048", // blue

          110: "#E5E5E5",
          111: "#A3A3A3",
          112: "#787878",
          113: "#616161",
          114: "#434343",
          115: "#2D2D2D",
        },
      },
      fontFamily: {
        sans: ["ui-sans-serif", "system-ui"],
        serif: ["ui-serif", "Georgia"],
        mono: ["ui-monospace", "SFMono-Regular"],
        display: ["Oswald"],
        body: ["Open Sans"],
        inter: ["Inter", "ui-sans-serif"],
        roboto: ["Roboto", "ui-sans-serif"],
        raleway: ["Raleway", "ui-sans-serif"],
        opensans: ["Open Sans", "ui-sans-serif"],
      },
    },
  },
};
