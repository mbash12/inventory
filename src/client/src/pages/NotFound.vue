<script setup>
    setTimeout(() => {
        const pos = { x: 0, y: 0 };
        const q = (e) => document.querySelector(e);
        q("#page").addEventListener("mousemove", (e) => {
            pos.x = e.x;
            pos.y = e.y;
            if (
                pos.x >= window.innerWidth / 2 - 200 &&
                pos.x <= window.innerWidth / 2 + 200 &&
                pos.y >= window.innerHeight / 2 - 120 &&
                pos.y <= window.innerHeight / 2 + 120
            ) {
                q("#cursor").classList.add(
                    "backdrop-filter",
                    "backdrop-blur-sm"
                );
            } else {
                q("#cursor").classList.remove(
                    "backdrop-filter",
                    "backdrop-blur-sm"
                );
            }
            q("#cursor").style.transform = `translate(${
                pos.x - window.innerWidth / 2
            }px,${pos.y - window.innerHeight / 2}px) ${
                pos.x >= window.innerWidth / 2 - 200 &&
                pos.x <= window.innerWidth / 2 + 200 &&
                pos.y >= window.innerHeight / 2 - 120 &&
                pos.y <= window.innerHeight / 2 + 120
                    ? "scale(6)"
                    : "scale(1)"
            }`;
            q("#bg").style.transform = `rotateX(${
                (pos.y - window.innerHeight / 2) * 0.1
            }deg) rotateZ(${
                (pos.x - window.innerWidth / 2) * 0.02
            }deg) translate(${(pos.x - window.innerWidth / 2) * 0.04}px,${
                (pos.y - window.innerHeight / 2) * 0.04
            }px)`;
            q("#bg").style.backgroundColor = `hsl(${
                (pos.x / window.innerWidth) * 360
            }, ${100 - (pos.y / window.innerHeight) * 50}%, 50%)`;
            q("#cursor").style.backgroundColor = `hsla(${
                (pos.x / window.innerWidth) * 360
            }, ${100 - (pos.y / window.innerHeight) * 50}%, 50%, ${
                pos.x >= window.innerWidth / 2 - 200 &&
                pos.x <= window.innerWidth / 2 + 200 &&
                pos.y >= window.innerHeight / 2 - 120 &&
                pos.y <= window.innerHeight / 2 + 120
                    ? "5%"
                    : "100%"
            })`;
            q("#tx").style.transform = `translate(${
                (pos.x - window.innerWidth / 2) * 0.08 * -1
            }px,${(pos.y - window.innerHeight / 2) * 0.08 * -1}px)`;
        });
    }, 100);
</script>
<template>
<main
    class="w-full h-full flex items-center  justify-center flex-col select-none mmain notfound"
    id="page"
>
    <div class="relative -mt-4 ">
        <span
            class="text-8xl font-bold mt-4 text-white  absolute w-full h-full flex items-end shadow-xl justify-center   bg-red-500 rounded-tr-2xl rounded-sm  "
            id="bg"
        />
        <span
            class="text-12xl font-bold text-[#00000022] filter drop-shadow-xl "
            id="nu">404</span
        >
        <span
            class="text-8xl font-bold mt-4 text-white  absolute w-full h-full flex items-end filter drop-shadow-xl justify-center -top-4 left-0 leading-18 ml-11 z-10"
            id="tx"
        >
            <span
                class="text-5xl transform -rotate-90  absolute -left-24 bottom-10 leading-13 opacity-30"
                >PAGE</span
            >
            Not Found</span
        >
    </div>
    <div id="cursor" class="absolute rounded-full w-10 h-10 bg-red-500" />
</main>
</template>
<style>
    .notfound * {
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .notfound #bg {
        perspective: 1000px;
        transform-style: preserve-3d;
    }
    .notfound.mmain {
        perspective: 1000px;
        transform-style: preserve-3d;
        cursor: none;
    }
</style>