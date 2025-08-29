export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md">
                <img src="/logo-icon.svg" alt="Catet Aja Logo" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="font-bold text-xl me-3 bg-gradient-to-r from-green-500 to-cyan-300 bg-clip-text text-transparent ">
                    Catet Aja
                </span>
            </div>
        </>
    );
}
