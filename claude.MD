This is a project for a wordpress plugin which enables the display of a specific form.
- This form will appear on only one specific post. What is the best method of implementing this? My guess is a shortcode. Let me know if you have a more efficient idea.
- The form takes user input and calculates an output. The corresponding javascript should be loaded only when the form is displayed, not otherwise (I'll exclude it from autoptimize).
- Inputs are ages of family members and planned visits in Barcelona, outputs are the combined ticket prices and the total cost
- I have prepared an xls file which shows the form to be generated: BarcelonaCalc.xlsx - let me know if I should install some tool which may help you better read the xls file
In this xls file you will find:
- 2 sheets: 1 for the "input-output" which contains the actual user form to be displayed and directly related data and a second sheet for the calculation ("calc") to be done in the background based on the user input
- the user form to be displayed is the area with a visible border 'input-output'!B2:E35
- column G contains tooltips to be displayed on hover over column E: 'input-output'!G19:G30 to be displayed over 'input-output'!E19:E30
- column I contains drop-down choices for the age selection: 'input-output'!I3:I28 for dropdowns in 'input-output'!E8:E13
- columns J to U contain drop-down choices for the cells in column B (see how the xls file is prepared for the detail - let me know if you need more information
- input cells are only drop-down choices: 'input-output'!E8:E13 for ages and 'input-output'!B19:B30 for visits
- the calc sheet contains both the actual ticket price information by visitor age and the calculation
- output of the calculation goes in cells 'input-output'!E19:E32 - let me know if you need more guidance
- store the ticket price information and other data/text susceptible to updates in a file easy to update by the admin - you can assume CLI access - no need for a corresponding admin page in the backend

Be proactive and inventive - the design should be responsive working on desktop (maximum content width is 960px) to mobile screens and visually aligned with the style of https://www.mamanvoyage.com/

### Safety and permissions

Allowed without prompt:
- read files, list files, write files, get any file (html, css, js) from http://www.mamanvoyage.com/

Note: proactively suggest installation of tools which would help with your task for installation.
