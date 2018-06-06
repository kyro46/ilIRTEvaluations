
# ilIRTEvaluations

### A Subplugin for IRT via OpenCPU for the ILIAS ExtendedTestStatistics Plugin ###

Integrating R  with ILIAS to evaluate tests (and maybe much more in the future).

### Usage ###

Install the plugin

```bash
mkdir -p Customizing/global/plugins/Modules/Test/Evaluations  
cd Customizing/global/plugins/Modules/Test/Evaluations
git clone https://github.com/kyro46/ilIRTEvaluations.git
```

You don't need to activate the plugin because it will be recognized by ExtendedTestStatistics automatically. But you have to set an URL for the REST-API of an [OpenCPU](https://www.opencpu.org/)-server in the ExtendedTestStatistics configuration.

OpenCPU is free software based on rApache and available as apt-repository package and CRAN. The public instance (https://cloud.opencpu.org) can be used in the configuration for now if you don't have an own instance. Transferred data is anonymized and encryption via SSL is supported.

### Features ###

##### Classical Test Theory #####

* Internal consistency (Cronbach's Alpha/Guttman's "Lambda 3"/Kuder–Richardson Formula 20 (KR 20))
* Internal consistency without a given item and the impact on the overall consistency

Factor analysis
* Scree plot
* Graph for factor loadings

##### Item Response Theory #####

Models
* Rasch Model
* One Parameter Logistic (1PL) Model 
* Zwo Parameter Logistic (2PL) Model 
* Graded Response Model
* Generalized Partial Credit Model

Plots for each model
* Item Response Category Characteristic Curves
* Item Information Curves
* Test Information Curve

Goodness of fit (TBD)
* Model fit
* Item fit
* Person fit

##### Various #####

Interactive R console
* Data from the test is prepared as R-dataframe "data"
* R-commands can be executed on this data inside the browser

Dichotomization (only if needed, not configurable yet)
* Mean
* Median
* Modus
* 50% of reachable points for the specific question (default)
* Specific value

### Credits ###
* Development by Christoph Jobst for etstat version 1.1.2+
* OpenCPU by Ooms, Jeroen. (2014). The OpenCPU System: Towards a Universal Interface for Scientific Computing through Separation of Concerns. [LINK](https://arxiv.org/abs/1406.4806)