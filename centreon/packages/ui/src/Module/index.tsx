import type { ThemeOptions } from "@mui/material";
import { createGenerateClassName, StylesProvider } from "@mui/styles";
import type { QueryClient } from "@tanstack/react-query";
import { type createStore, Provider as JotaiProvider } from "jotai";
import { QueryProvider, ThemeProvider } from "..";
import SnackbarProvider from "../Snackbar/SnackbarProvider";

export interface ModuleProps {
	children: React.ReactElement;
	maxSnackbars?: number;
	queryClient?: QueryClient;
	seedName: string;
	store: ReturnType<typeof createStore>;
	overrideTheme?: {
		light: Partial<ThemeOptions>;
		dark: Partial<ThemeOptions>;
	};
}

const Module = ({
	children,
	seedName,
	maxSnackbars = 3,
	store,
	queryClient,
	overrideTheme,
}: ModuleProps): JSX.Element => {
	const generateClassName = createGenerateClassName({
		seed: seedName,
	});

	return (
		<QueryProvider queryClient={queryClient}>
			<JotaiProvider store={store}>
				<StylesProvider generateClassName={generateClassName}>
					<ThemeProvider overrideTheme={overrideTheme}>
						<SnackbarProvider maxSnackbars={maxSnackbars}>
							{children}
						</SnackbarProvider>
					</ThemeProvider>
				</StylesProvider>
			</JotaiProvider>
		</QueryProvider>
	);
};

export default Module;
