
# ilIRTEvaluations

### A Subplugin for IRT via OpenCPU for the ILIAS ExtendedTestStatistics Plugin

Integrating R with ILIAS to evaluate tests (and maybe much more in the future).

### Usage

Install the plugin

```bash
mkdir -p Customizing/global/plugins/Modules/Test/Evaluations
cd Customizing/global/plugins/Modules/Test/Evaluations
git clone https://github.com/kyro46/ilIRTEvaluations.git
```

You have to set an URL for the REST-API of an [OpenCPU](https://www.opencpu.org/)-server in the ExtendedTestStatistics configuration.

OpenCPU is free software based on rApache and available as apt-repository package and CRAN. Transferred data is anonymized and encryption via SSL is supported. The public instance (https://cloud.opencpu.org) can't be used because some required packages are not installed there.

### Features

The available pages might be selected in the plugin administration.

#### Classical Test Theory (CTT)

- [x] Internal consistency (Cronbach's Alpha/Guttman's "Lambda 3"/Kuder–Richardson Formula 20 (KR 20))
- [x] Internal consistency without a given item and the impact on the overall consistency
- [ ] Empirical Item Characteristic Curves
- [x] Suggested test length to reach a desired reliability (Spearman-Brown-Formula)

Factor analysis

- [x] Scree plot
- [x] Graph for factor loadings

Raw score analysis

- [x] Distribution
- [x] Skewness
- [x] Kurtosis

#### Item Response Theory (IRT)

##### Plots and difficulty/discrimination according to various models (R-Package LTM)

- [x] Rasch Model
- [x] One Parameter Logistic (1PL) Model (common discrimination <> 1)
- [x] Two Parameter Logistic (2PL) Model 
- [x] Three Parameter Logistic (3PL) Model
- [x] Graded Response Model
- [x] Generalized Partial Credit Model

Plots for each model

- [x] Item Response Category Characteristic Curves
- [x] Item Information Curves
- [x] Test Information Curve

##### Focus on the Graded Response Model (R-Package MIRT)

- [x] Difficulty and discrimination per Item
- [x] Model fit (AIC, AICc, BIC, SABIC, HQ, Convergence)
- [x] Item fit (Zh, signed Chi-Square)
- [x] Person fit (Zh)
- [x] Person ability

Plots

- [x] Expected total score
- [x] Test Information and Standard Errors
- [x] Item Tracelines
- [x] Model-fit comparison

Additional comparison of evaluations following CTT and IRT

- [x] Difficulty correlation
- [x] Discrimination correlation
- [x] Sumscore vs. estimated ability correlation
- [ ] Empirical Item Characteristic Curves vs. IRT Item Tracelines
- [ ] Observable factors vs. fit of multidimensional IRT

##### Various #####

Interactive R console
* Data from the test is prepared as R-dataframe "data"
* R-commands can be executed on this data and are shown via knitr inside the browser

Dichotomization (to use dichotomous models with polytomous data), selectable in plugin-config
* Mean
* Median (default, see [LINK](https://www.doi.org/10.1037%2F1082-989X.7.1.19))
* Modus
* 50% of reachable points for the specific question 

### Credits ###
* Development by Christoph Jobst for etstat version 1.1.2+
* OpenCPU by Ooms, Jeroen. (2014). The OpenCPU System: Towards a Universal Interface for Scientific Computing through Separation of Concerns. [LINK](https://arxiv.org/abs/1406.4806)