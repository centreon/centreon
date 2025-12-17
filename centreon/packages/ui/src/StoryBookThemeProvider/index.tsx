import type { ThemeMode } from "@centreon/ui-context";

import {
	CssBaseline,
	createTheme,
	ThemeProvider as MuiThemeProvider,
	StyledEngineProvider,
} from "@mui/material";
import { GlobalStyles } from "@mui/system";
import { type ReactElement, useMemo } from "react";
import { getTheme } from "../ThemeProvider";

interface Props {
	children: ReactElement;
	themeMode: ThemeMode;
}

const StoryBookThemeProvider = ({
	children,
	themeMode,
}: Props): JSX.Element => {
	const theme = useMemo(() => createTheme(getTheme(themeMode)), [themeMode]);

	return (
		<StyledEngineProvider injectFirst enableCssLayer>
			<GlobalStyles styles="@layer theme,base,mui,components,utilities;" />
			<MuiThemeProvider theme={theme}>
				{children}
				<CssBaseline />
			</MuiThemeProvider>
		</StyledEngineProvider>
	);
};

export default StoryBookThemeProvider;
