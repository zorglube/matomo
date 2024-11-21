/*!
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
import NumberFormatter from './NumberFormatter';

const formats: any = {
  ar: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0%",
    "patternCurrency": "‏#,##0.00 ¤",
    "symbolPlus": "؜+",
    "symbolMinus": "؜-",
    "symbolPercent": "٪؜",
    "symbolGroup": "٬",
    "symbolDecimal": "٫"
  },
  be: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0 %",
    "patternCurrency": "#,##0.00 ¤",
    "symbolPlus": "+",
    "symbolMinus": "-",
    "symbolPercent": "%",
    "symbolGroup": " ",
    "symbolDecimal": ","
  },
  de: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0 %",
    "patternCurrency": "#,##0.00 ¤",
    "symbolPlus": "+",
    "symbolMinus": "-",
    "symbolPercent": "%",
    "symbolGroup": ".",
    "symbolDecimal": ","
  },
  en: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0%",
    "patternCurrency": "¤#,##0.00",
    "symbolPlus": "+",
    "symbolMinus": "-",
    "symbolPercent": "%",
    "symbolGroup": ",",
    "symbolDecimal": "."
  },
  he: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0%",
    "patternCurrency": "‏#,##0.00 ‏¤;‏-#,##0.00 ‏¤",
    "symbolPlus": "‎+",
    "symbolMinus": "‎-",
    "symbolPercent": "%",
    "symbolGroup": ",",
    "symbolDecimal": "."
  },
  hi: {
    "patternNumber": "#,##,##0.###",
    "patternPercent": "#,##,##0%",
    "patternCurrency": "¤#,##,##0.00",
    "symbolPlus": "+",
    "symbolMinus": "-",
    "symbolPercent": "%",
    "symbolGroup": ",",
    "symbolDecimal": "."
  },
  lt: {
    "patternNumber": "#,##0.###",
    "patternPercent": "#,##0 %",
    "patternCurrency": "#,##0.00 ¤",
    "symbolPlus": "+",
    "symbolMinus": "−",
    "symbolPercent": "%",
    "symbolGroup": " ",
    "symbolDecimal": ","
  },
};

describe('CoreHome/NumberFormatter', () => {

  const numberTestData: Array<Array<any>> = [
    // english formats
    ['en', 5, 0, 0, '5'],
    ['en', -5, 0, 3, '-5'],
    ['en', 5.299, 0, 0, '5'],
    ['en', 5.2992, 3, 0, '5.299'],
    ['en', 5.6666666666667, 1, 0, '5.7'],
    ['en', 5.07, 1, 0, '5.1'],
    ['en', -50, 3, 3, '-50.000'],
    ['en', 5000, 0, 0, '5,000'],
    ['en', 5000000, 0, 0, '5,000,000'],
    ['en', -5000000, 0, 0, '-5,000,000'],

    // foreign languages
    ['ar', 51239.56, 3, 0, '51٬239٫56'],
    ['be', 51239.56, 3, 0, '51 239,56'],
    ['de', 51239.56, 3, 0, '51.239,56'],
    ['he', 152551239.56, 3, 0, '152,551,239.56'],
    ['he', -152551239.56, 3, 0, '‎-152,551,239.56'],
    ['hi', 152551239.56, 0, 0, '15,25,51,240'],
    ['lt', -152551239.56, 0, 0, '−152 551 240'],
  ];

  numberTestData.forEach((testdata) => {
    const [ lang, input, maxFractionDigits, minFractionDigits, expected ] = testdata;

    it(`should correctly format number with (${lang}, ${input}, ${maxFractionDigits}, ${minFractionDigits})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.formatNumber(input as number, maxFractionDigits as number, minFractionDigits as number);

      expect(result).toEqual(expected);
    })
  });

  const percentNumberTestData: Array<Array<any>> = [
    // english formats
    ['en', 5, 0, 0, '5%'],
    ['en', -5, 0, 3, '-5%'],
    ['en', 5.299, 0, 0, '5%'],
    ['en', 5.2992, 3, 0, '5.299%'],
    ['en', -50, 3, 3, '-50.000%'],
    ['en', -50, 1, 1, '-50.0%'],
    ['en', -50.1, 3, 3, '-50.100%'],
    ['en', 5000, 0, 0, '5,000%'],
    ['en', +5000, 0, 0, '5,000%'],
    ['en', 5000000, 0, 0, '5,000,000%'],
    ['en', -5000000, 0, 0, '-5,000,000%'],

    // foreign languages
    ['ar', 51239.56, 3, 0, '51٬239٫56٪؜'],
    ['be', 51239.56, 3, 0, '51 239,56 %'],
    ['de', 51239.56, 3, 0, '51.239,56 %'],
    ['he', 152551239.56, 3, 0, '152,551,239.56%'],
    ['hi', 152551239.56, 0, 0, '15,25,51,240%'],
    ['lt', -152551239.56, 0, 0, '−152 551 240 %'],
  ];

  percentNumberTestData.forEach((testdata) => {
    const [ lang, input, maxFractionDigits, minFractionDigits, expected ] = testdata;

    it(`should correctly format percent with (${lang}, ${input}, ${maxFractionDigits}, ${minFractionDigits})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.formatPercent(input as number, maxFractionDigits as number, minFractionDigits as number);

      expect(result).toEqual(expected);
    })
  });

  const currencyTestData: Array<Array<any>> = [
    // english formats
    ['en', 5, '$', 0, 0, '$5'],
    ['en', -5, '$', 0, 3, '-$5'],
    ['en', 5.299, '$', 0, 0, '$5'],
    ['en', 5.2992, '$', 3, 0, '$5.299'],
    ['en', -50, '$', 3, 3, '-$50.000'],
    ['en', -50, '$', 1, 1, '-$50.0'],
    ['en', -50.1, '$', 3, 3, '-$50.100'],
    ['en', 5000, '$', 0, 0, '$5,000'],
    ['en', +5000, '$', 0, 0, '$5,000'],
    ['en', 5000000, '$', 0, 0, '$5,000,000'],
    ['en', -5000000, '$', 0, 0, '-$5,000,000'],

    // foreign languages
    ['ar', 51239.56, '$', 3, 0, '‏51٬239٫56 $'],
    ['be', 51239.56, '$', 3, 0, '51 239,56 $'],
    ['de', 51239.56, '$', 3, 0, '51.239,56 $'],
    ['he', -152551239.56, '$', 3, 0, '‏‎-152,551,239.56 ‏$'],
    ['hi', 152551239.56, '$', 0, 0, '$15,25,51,240'],
    ['lt', -152551239.56, '$', 0, 0, '−152 551 240 $'],
  ];

  currencyTestData.forEach((testdata) => {
    const [ lang, input, currency, maxFractionDigits, minFractionDigits, expected ] = testdata;

    it(`should correctly format currency with (${lang}, ${input}, ${currency}, ${maxFractionDigits}, ${minFractionDigits})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.formatCurrency(input as number, currency as string, maxFractionDigits as number, minFractionDigits as number);

      expect(result).toEqual(expected);
    })
  });

  const evolutionTestData: Array<Array<any>> = [
    // english formats
    ['en', 5, 0, 0, '+5%'],
    ['en', -5, 0, 3, '-5%'],
    ['en', 5.299, 0, 0, '+5%'],
    ['en', 5.299, 3, 0, '+5.299%'],
    ['en', -50, 3, 3, '-50.000%'],
    ['en', -50, 1, 1, '-50.0%'],
    ['en', -50.1, 3, 3, '-50.100%'],
    ['en', 5000, 0, 0, '+5,000%'],
    ['en', +5000, 0, 0, '+5,000%'],
    ['en', 5000000, 0, 0, '+5,000,000%'],
    ['en', -5000000, 0, 0, '-5,000,000%'],

    // foreign languages
    ['ar', 51239.56, 3, 0, '؜+51٬239٫56٪؜'],
    ['be', 51239.56, 3, 0, '+51 239,56 %'],
    ['de', 51239.56, 3, 0, '+51.239,56 %'],
    ['he', 152551239.56, 3, 0, '‎+152,551,239.56%'],
    ['hi', 152551239.56, 0, 0, '+15,25,51,240%'],
    ['lt', -152551239.56, 0, 0, '−152 551 240 %'],
  ];

  evolutionTestData.forEach((testdata) => {
    const [ lang, input, maxFractionDigits, minFractionDigits, expected ] = testdata;

    it(`should correctly format evolution with (${lang}, ${input}, ${maxFractionDigits}, ${minFractionDigits})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.formatEvolution(input as number, maxFractionDigits as number, minFractionDigits as number);

      expect(result).toEqual(expected);
    })
  });

  const calculateAndFormatEvolutionTestData: Array<Array<any>> = [
    // we test only english, as other formats are already covered by formatEvolution tests
    ['en', 2, 1, false, '+100%'],
    ['en', 25, 100, false, '-75%'],
    ['en', 1, 3, false, '-66.7%'],
    ['en', 1, 3, true, '66.7%'],
    ['en', 10001, 9883, false, '+1.19%'],
    ['en', 100001, 100000, false, '+0.001%'],
    ['en', 100001, 100000, true, '0.001%'],
    ['en', 10000001, 10000000, false, '+0%'],
  ];

  calculateAndFormatEvolutionTestData.forEach((testdata) => {
    const [ lang, input1, input2, noSign, expected ] = testdata;

    it(`should correctly format evolution with (${lang}, ${input1}, ${input2}, ${noSign})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.calculateAndFormatEvolution(input1 as number, input2 as number, noSign);

      expect(result).toEqual(expected);
    })
  });

  const formattedNumberTestData: Array<Array<any>> = [
    ['en', '+100%', 100],
    ['en', '-75%', -75],
    ['en', '12,245.66', 12245.66],
    ['en', '-0.555', -0.555],
    ['ar', '؜+51٬239٫56٪؜', 51239.56],
    ['be', '+51 239,56 %', 51239.56],
    ['de', '+51.239,56 %', 51239.56 ],
    ['de', '-239,56 $%', -239.56 ],
    ['he', '‎+152,551,239.56%', 152551239.56],
    ['he', '‎-152,551,239.56', -152551239.56],
    ['hi', '+15,25,51,240%', 152551240],
    ['lt', '−152 551 240 %', -152551240],
  ];

  formattedNumberTestData.forEach((testdata) => {
    const [ lang, input, expected ] = testdata;

    it(`should correctly parse formatted number with (${lang}, ${input})`, () => {

      window.piwik.numbers = formats[lang];

      const result = NumberFormatter.parseFormattedNumber(input as string);

      expect(result).toEqual(expected);
    })
  });

});
