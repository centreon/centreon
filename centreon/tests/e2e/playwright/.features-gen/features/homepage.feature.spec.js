// Generated from: features/homepage.feature
import { test } from "../../features/steps/fixtures.ts";

test.describe('Home Page', () => {

  test('Check title', async ({ Given, Then }) => { 
    await Given('I am on home page'); 
    await Then('I see in title "Playwright"'); 
  });

  test('Check get started', async ({ Given, When, Then }) => { 
    await Given('I am on home page'); 
    await When('I click link "Get started"'); 
    await Then('I see in title "Installation"'); 
  });

});

// == technical section ==

test.use({
  $test: [({}, use) => use(test), { scope: 'test', box: true }],
  $uri: [({}, use) => use('features/homepage.feature'), { scope: 'test', box: true }],
  $bddFileData: [({}, use) => use(bddFileData), { scope: "test", box: true }],
  $world: [({ world }, use) => use(world), { scope: 'test', box: true }],
});

const bddFileData = [ // bdd-data-start
  {"pwTestLine":6,"pickleLine":3,"tags":[],"steps":[{"pwStepLine":7,"gherkinStepLine":4,"keywordType":"Context","textWithKeyword":"Given I am on home page","stepMatchArguments":[]},{"pwStepLine":8,"gherkinStepLine":5,"keywordType":"Outcome","textWithKeyword":"Then I see in title \"Playwright\"","stepMatchArguments":[{"group":{"start":15,"value":"\"Playwright\"","children":[{"start":16,"value":"Playwright","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]}]},
  {"pwTestLine":11,"pickleLine":7,"tags":[],"steps":[{"pwStepLine":12,"gherkinStepLine":8,"keywordType":"Context","textWithKeyword":"Given I am on home page","stepMatchArguments":[]},{"pwStepLine":13,"gherkinStepLine":9,"keywordType":"Action","textWithKeyword":"When I click link \"Get started\"","stepMatchArguments":[{"group":{"start":13,"value":"\"Get started\"","children":[{"start":14,"value":"Get started","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]},{"pwStepLine":14,"gherkinStepLine":10,"keywordType":"Outcome","textWithKeyword":"Then I see in title \"Installation\"","stepMatchArguments":[{"group":{"start":15,"value":"\"Installation\"","children":[{"start":16,"value":"Installation","children":[{"children":[]}]},{"children":[{"children":[]}]}]},"parameterTypeName":"string"}]}]},
]; // bdd-data-end