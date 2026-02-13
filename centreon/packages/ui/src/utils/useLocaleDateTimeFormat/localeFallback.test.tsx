import dayjs from "dayjs";
import { useEffect } from "react";
import "dayjs/locale/en";

import { ThemeMode, userAtom } from "@centreon/ui-context";
import { type RenderResult, render } from "@testing-library/react";
import localizedFormatPlugin from "dayjs/plugin/localizedFormat";
import timezonePlugin from "dayjs/plugin/timezone";
import utcPlugin from "dayjs/plugin/utc";
import { Provider, useSetAtom } from "jotai";

import { useLocaleDateTimeFormat } from ".";

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

// biome-ignore lint/suspicious/noImplicitAnyLet: need it
let context;

const TestComponent = (): JSX.Element => {
	const localeDateTimeFormat = useLocaleDateTimeFormat();
	const setUser = useSetAtom(userAtom);

	useEffect(() => {
		setUser({
			alias: "admin",
			default_page: "/monitoring/resources",
			isExportButtonEnabled: false,
			locale: "unsupported_locale",
			name: "admin",
			themeMode: ThemeMode.light,
			timezone: "Europe/Paris",
			use_deprecated_pages: false,
		});
	}, [setUser]);

	context = localeDateTimeFormat;

	return <div />;
};

const renderLocaleDateTimeFormat = (): RenderResult => {
	return render(
		<Provider>
			<TestComponent />
		</Provider>,
	);
};

describe(useLocaleDateTimeFormat, () => {
	describe("toHumanizedDuration", () => {
		it("formats the given duration in English to a humanized duration when the locale is unsupported", () => {
			renderLocaleDateTimeFormat();

			const formattedDateTime = context.toHumanizedDuration(22141);

			expect(formattedDateTime).toEqual("6h 9m 1s");
		});
	});
});
