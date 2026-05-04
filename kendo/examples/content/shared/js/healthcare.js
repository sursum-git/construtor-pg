const patients = [
  {
    "id": 1,
    "name": "Emma Johnson",
    "age": 45,
    "conditionSeverity": "Critical",
    "department": "Anesthesiology",
    "admissionDate": "2024-07-15T08:30:00Z",
    "status": "Critical",
    "riskScore": 85,
    "diagnosis": "Acute respiratory failure",
    "treatmentPlanStatus": "Active",
    "medicationCount": 8,
    "riskIndicators": "High blood pressure, diabetes",
    "recentLabResults": [
      "WBC: 12.5",
      "Hemoglobin: 8.2",
      "Creatinine: 2.1"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 2,
    "name": "Lucas Brown",
    "age": 32,
    "conditionSeverity": "Moderate",
    "department": "Anesthesiology",
    "admissionDate": "2024-07-20T14:15:00Z",
    "status": "Under Treatment",
    "riskScore": 45,
    "diagnosis": "Post-operative pain management",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 4,
    "riskIndicators": "None significant",
    "recentLabResults": [
      "WBC: 7.2",
      "Hemoglobin: 13.1",
      "Platelets: 250"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 3,
    "name": "Olivia Davis",
    "age": 67,
    "conditionSeverity": "Severe",
    "department": "Anesthesiology",
    "admissionDate": "2024-07-12T10:45:00Z",
    "status": "Under Treatment",
    "riskScore": 78,
    "diagnosis": "Complex cardiac surgery recovery",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 12,
    "riskIndicators": "Cardiac arrhythmia, hypertension",
    "recentLabResults": [
      "Troponin: 0.8",
      "BNP: 450",
      "D-Dimer: 2.1"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 4,
    "name": "James Wilson",
    "age": 28,
    "conditionSeverity": "Mild",
    "department": "Anesthesiology",
    "admissionDate": "2024-07-25T09:20:00Z",
    "status": "Discharged",
    "riskScore": 25,
    "diagnosis": "Minor surgical procedure",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "No significant risks",
    "recentLabResults": [
      "WBC: 6.8",
      "Hemoglobin: 14.2",
      "Glucose: 95"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 5,
    "name": "Sophia Martinez",
    "age": 55,
    "conditionSeverity": "Critical",
    "department": "Anesthesiology",
    "admissionDate": "2024-07-08T16:30:00Z",
    "status": "Critical",
    "riskScore": 92,
    "diagnosis": "Septic shock",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 15,
    "riskIndicators": "Multi-organ dysfunction",
    "recentLabResults": [
      "Lactate: 4.2",
      "Procalcitonin: 8.5",
      "WBC: 18.9"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 6,
    "name": "Michael Anderson",
    "age": 41,
    "conditionSeverity": "Moderate",
    "department": "Cardiology",
    "admissionDate": "2024-07-22T11:15:00Z",
    "status": "Under Treatment",
    "riskScore": 58,
    "diagnosis": "Atrial fibrillation",
    "treatmentPlanStatus": "Active",
    "medicationCount": 6,
    "riskIndicators": "Hypertension, obesity",
    "recentLabResults": [
      "INR: 2.3",
      "BNP: 220",
      "Creatinine: 1.2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 7,
    "name": "Isabella Thompson",
    "age": 29,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-28T19:45:00Z",
    "status": "Stable",
    "riskScore": 22,
    "diagnosis": "Minor laceration repair",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 1,
    "riskIndicators": "None",
    "recentLabResults": [
      "WBC: 7.1",
      "Hemoglobin: 13.8",
      "Platelets: 280"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 8,
    "name": "Daniel Garcia",
    "age": 63,
    "conditionSeverity": "Severe",
    "department": "ICU",
    "admissionDate": "2024-07-10T13:20:00Z",
    "status": "Critical",
    "riskScore": 81,
    "diagnosis": "Acute myocardial infarction",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 11,
    "riskIndicators": "Diabetes, smoking history",
    "recentLabResults": [
      "Troponin: 15.2",
      "CK-MB: 28",
      "Cholesterol: 285"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 9,
    "name": "Mia Rodriguez",
    "age": 38,
    "conditionSeverity": "Moderate",
    "department": "Orthopedics",
    "admissionDate": "2024-07-24T08:30:00Z",
    "status": "Under Treatment",
    "riskScore": 42,
    "diagnosis": "Fractured tibia",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "Osteoporosis",
    "recentLabResults": [
      "Calcium: 8.9",
      "Vitamin D: 22",
      "WBC: 8.3"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 10,
    "name": "Ethan Williams",
    "age": 52,
    "conditionSeverity": "Critical",
    "department": "Neurology",
    "admissionDate": "2024-07-05T22:15:00Z",
    "status": "Critical",
    "riskScore": 88,
    "diagnosis": "Ischemic stroke",
    "treatmentPlanStatus": "Active",
    "medicationCount": 9,
    "riskIndicators": "Hypertension, atrial fibrillation",
    "recentLabResults": [
      "INR: 1.8",
      "Glucose: 180",
      "CT scan: Infarct confirmed"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 11,
    "name": "Charlotte Miller",
    "age": 71,
    "conditionSeverity": "Moderate",
    "department": "Geriatrics",
    "admissionDate": "2024-07-18T14:00:00Z",
    "status": "Stable",
    "riskScore": 48,
    "diagnosis": "Pneumonia",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 7,
    "riskIndicators": "COPD, advanced age",
    "recentLabResults": [
      "WBC: 14.2",
      "Procalcitonin: 0.8",
      "O2 Saturation: 92%"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 12,
    "name": "Alexander Davis",
    "age": 34,
    "conditionSeverity": "Mild",
    "department": "Surgery",
    "admissionDate": "2024-07-26T10:45:00Z",
    "status": "Under Treatment",
    "riskScore": 28,
    "diagnosis": "Appendectomy recovery",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "None significant",
    "recentLabResults": [
      "WBC: 9.1",
      "CRP: 2.1",
      "Temperature: 98.6°F"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 13,
    "name": "Amelia Johnson",
    "age": 46,
    "conditionSeverity": "Severe",
    "department": "Oncology",
    "admissionDate": "2024-07-14T16:30:00Z",
    "status": "Under Treatment",
    "riskScore": 76,
    "diagnosis": "Breast cancer treatment",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 8,
    "riskIndicators": "Immunosuppression",
    "recentLabResults": [
      "WBC: 3.2",
      "Neutrophils: 1.8",
      "Hemoglobin: 9.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 14,
    "name": "Benjamin Wilson",
    "age": 59,
    "conditionSeverity": "Critical",
    "department": "Pulmonology",
    "admissionDate": "2024-07-11T07:20:00Z",
    "status": "Critical",
    "riskScore": 84,
    "diagnosis": "ARDS",
    "treatmentPlanStatus": "Active",
    "medicationCount": 12,
    "riskIndicators": "Mechanical ventilation",
    "recentLabResults": [
      "PaO2/FiO2: 180",
      "PEEP: 12",
      "pH: 7.32"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 15,
    "name": "Harper Martinez",
    "age": 25,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-29T20:15:00Z",
    "status": "Discharged",
    "riskScore": 18,
    "diagnosis": "Food poisoning",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "Dehydration",
    "recentLabResults": [
      "Electrolytes: Normal",
      "WBC: 11.8",
      "Stool culture: Pending"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 16,
    "name": "William Brown",
    "age": 68,
    "conditionSeverity": "Severe",
    "department": "Nephrology",
    "admissionDate": "2024-07-09T12:30:00Z",
    "status": "Under Treatment",
    "riskScore": 79,
    "diagnosis": "Acute kidney injury",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 10,
    "riskIndicators": "Diabetes, hypertension",
    "recentLabResults": [
      "Creatinine: 4.8",
      "BUN: 85",
      "Potassium: 5.2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 17,
    "name": "Evelyn Taylor",
    "age": 37,
    "conditionSeverity": "Moderate",
    "department": "Obstetrics",
    "admissionDate": "2024-07-21T09:45:00Z",
    "status": "Under Treatment",
    "riskScore": 35,
    "diagnosis": "Preeclampsia",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 4,
    "riskIndicators": "High blood pressure",
    "recentLabResults": [
      "BP: 160/100",
      "Protein: 3+",
      "Platelet count: 180"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 18,
    "name": "Logan Anderson",
    "age": 44,
    "conditionSeverity": "Critical",
    "department": "Trauma",
    "admissionDate": "2024-07-07T18:00:00Z",
    "status": "Critical",
    "riskScore": 91,
    "diagnosis": "Multiple trauma",
    "treatmentPlanStatus": "Active",
    "medicationCount": 14,
    "riskIndicators": "Internal bleeding, head injury",
    "recentLabResults": [
      "Hemoglobin: 6.8",
      "GCS: 12",
      "Lactate: 3.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 19,
    "name": "Abigail Thomas",
    "age": 31,
    "conditionSeverity": "Mild",
    "department": "Dermatology",
    "admissionDate": "2024-07-27T15:20:00Z",
    "status": "Stable",
    "riskScore": 20,
    "diagnosis": "Cellulitis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "None",
    "recentLabResults": [
      "WBC: 8.7",
      "CRP: 1.8",
      "Blood glucose: 98"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 20,
    "name": "Mason Jackson",
    "age": 56,
    "conditionSeverity": "Moderate",
    "department": "Gastroenterology",
    "admissionDate": "2024-07-19T11:30:00Z",
    "status": "Under Treatment",
    "riskScore": 52,
    "diagnosis": "Upper GI bleeding",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 6,
    "riskIndicators": "Peptic ulcer history",
    "recentLabResults": [
      "Hemoglobin: 8.9",
      "Hematocrit: 26%",
      "H. pylori: Positive"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 21,
    "name": "Ella White",
    "age": 73,
    "conditionSeverity": "Severe",
    "department": "Cardiology",
    "admissionDate": "2024-07-13T06:15:00Z",
    "status": "Under Treatment",
    "riskScore": 82,
    "diagnosis": "Congestive heart failure",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 9,
    "riskIndicators": "Advanced age, multiple comorbidities",
    "recentLabResults": [
      "BNP: 1850",
      "Ejection fraction: 35%",
      "Sodium: 128"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 22,
    "name": "Jacob Harris",
    "age": 42,
    "conditionSeverity": "Moderate",
    "department": "Psychiatry",
    "admissionDate": "2024-07-23T17:45:00Z",
    "status": "Under Treatment",
    "riskScore": 38,
    "diagnosis": "Severe depression with suicidal ideation",
    "treatmentPlanStatus": "Active",
    "medicationCount": 3,
    "riskIndicators": "Suicide risk, substance abuse history",
    "recentLabResults": [
      "Drug screen: Negative",
      "Liver function: Normal",
      "TSH: 2.4"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 23,
    "name": "Avery Clark",
    "age": 27,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-30T21:30:00Z",
    "status": "Discharged",
    "riskScore": 16,
    "diagnosis": "Allergic reaction",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "Known allergies",
    "recentLabResults": [
      "Tryptase: Normal",
      "IgE: Elevated",
      "Complete recovery"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 24,
    "name": "Carter Lewis",
    "age": 65,
    "conditionSeverity": "Critical",
    "department": "ICU",
    "admissionDate": "2024-07-06T03:45:00Z",
    "status": "Critical",
    "riskScore": 89,
    "diagnosis": "Multi-organ failure",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 16,
    "riskIndicators": "Sepsis, renal failure, respiratory failure",
    "recentLabResults": [
      "Creatinine: 5.2",
      "Bilirubin: 4.8",
      "Platelets: 95"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 25,
    "name": "Sofia Robinson",
    "age": 39,
    "conditionSeverity": "Moderate",
    "department": "Endocrinology",
    "admissionDate": "2024-07-17T13:00:00Z",
    "status": "Under Treatment",
    "riskScore": 44,
    "diagnosis": "Diabetic ketoacidosis",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "Type 1 diabetes, poor compliance",
    "recentLabResults": [
      "Glucose: 420",
      "Ketones: 3+",
      "pH: 7.18"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 26,
    "name": "Owen Walker",
    "age": 48,
    "conditionSeverity": "Severe",
    "department": "Hematology",
    "admissionDate": "2024-07-16T10:20:00Z",
    "status": "Under Treatment",
    "riskScore": 74,
    "diagnosis": "Acute leukemia",
    "treatmentPlanStatus": "Active",
    "medicationCount": 8,
    "riskIndicators": "Immunosuppression, bleeding risk",
    "recentLabResults": [
      "WBC: 78,000",
      "Platelets: 12,000",
      "Blast cells: 85%"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 27,
    "name": "Grace Hall",
    "age": 33,
    "conditionSeverity": "Mild",
    "department": "Internal Medicine",
    "admissionDate": "2024-07-28T08:15:00Z",
    "status": "Stable",
    "riskScore": 24,
    "diagnosis": "Viral gastroenteritis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 1,
    "riskIndicators": "Mild dehydration",
    "recentLabResults": [
      "Electrolytes: Normal",
      "WBC: 6.2",
      "Viral panel: Norovirus positive"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 28,
    "name": "Liam Allen",
    "age": 61,
    "conditionSeverity": "Critical",
    "department": "Cardiology",
    "admissionDate": "2024-07-04T14:30:00Z",
    "status": "Critical",
    "riskScore": 87,
    "diagnosis": "Cardiogenic shock",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 13,
    "riskIndicators": "Low ejection fraction, hypotension",
    "recentLabResults": [
      "Troponin: 28.4",
      "BNP: 2100",
      "Lactate: 4.6"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 29,
    "name": "Zoe Young",
    "age": 26,
    "conditionSeverity": "Moderate",
    "department": "Rheumatology",
    "admissionDate": "2024-07-25T12:45:00Z",
    "status": "Under Treatment",
    "riskScore": 41,
    "diagnosis": "Lupus flare",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 6,
    "riskIndicators": "Immunosuppression, kidney involvement",
    "recentLabResults": [
      "ANA: 1:640",
      "Anti-dsDNA: Positive",
      "Complement C3: Low"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 30,
    "name": "Noah King",
    "age": 54,
    "conditionSeverity": "Severe",
    "department": "Pulmonology",
    "admissionDate": "2024-07-12T19:00:00Z",
    "status": "Under Treatment",
    "riskScore": 77,
    "diagnosis": "Pulmonary embolism",
    "treatmentPlanStatus": "Active",
    "medicationCount": 7,
    "riskIndicators": "Recent surgery, immobilization",
    "recentLabResults": [
      "D-Dimer: 8.2",
      "CT-PE: Multiple emboli",
      "Troponin: 0.12"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 31,
    "name": "Lily Scott",
    "age": 40,
    "conditionSeverity": "Mild",
    "department": "Neurology",
    "admissionDate": "2024-07-29T11:20:00Z",
    "status": "Under Treatment",
    "riskScore": 30,
    "diagnosis": "Migraine with aura",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "Frequent episodes",
    "recentLabResults": [
      "MRI: Normal",
      "ESR: 8",
      "B12: Normal"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 32,
    "name": "Henry Adams",
    "age": 70,
    "conditionSeverity": "Critical",
    "department": "Geriatrics",
    "admissionDate": "2024-07-08T23:15:00Z",
    "status": "Critical",
    "riskScore": 93,
    "diagnosis": "Hip fracture with complications",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 11,
    "riskIndicators": "Advanced age, multiple comorbidities, delirium",
    "recentLabResults": [
      "Hemoglobin: 7.2",
      "Creatinine: 2.8",
      "Albumin: 2.1"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 33,
    "name": "Chloe Baker",
    "age": 35,
    "conditionSeverity": "Moderate",
    "department": "Emergency",
    "admissionDate": "2024-07-26T16:30:00Z",
    "status": "Under Treatment",
    "riskScore": 46,
    "diagnosis": "Severe asthma exacerbation",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "Previous intubations",
    "recentLabResults": [
      "Peak flow: 180",
      "ABG: pH 7.45, PCO2 32",
      "Eosinophils: 8%"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 34,
    "name": "Ryan Green",
    "age": 49,
    "conditionSeverity": "Severe",
    "department": "Urology",
    "admissionDate": "2024-07-15T07:45:00Z",
    "status": "Under Treatment",
    "riskScore": 71,
    "diagnosis": "Kidney stones with obstruction",
    "treatmentPlanStatus": "Active",
    "medicationCount": 6,
    "riskIndicators": "Hydronephrosis, infection",
    "recentLabResults": [
      "Creatinine: 2.2",
      "WBC: 15.8",
      "Urinalysis: RBC 3+, WBC 3+"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 35,
    "name": "Maya Nelson",
    "age": 28,
    "conditionSeverity": "Mild",
    "department": "OB-GYN",
    "admissionDate": "2024-07-30T14:00:00Z",
    "status": "Stable",
    "riskScore": 19,
    "diagnosis": "Normal delivery",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "None",
    "recentLabResults": [
      "Hemoglobin: 11.8",
      "Platelets: 320",
      "Post-delivery normal"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 36,
    "name": "Connor Carter",
    "age": 57,
    "conditionSeverity": "Critical",
    "department": "Neurosurgery",
    "admissionDate": "2024-07-03T01:30:00Z",
    "status": "Critical",
    "riskScore": 86,
    "diagnosis": "Subdural hematoma",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 10,
    "riskIndicators": "Increased intracranial pressure",
    "recentLabResults": [
      "CT scan: Midline shift",
      "ICP: 25 mmHg",
      "GCS: 10"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 37,
    "name": "Aria Mitchell",
    "age": 43,
    "conditionSeverity": "Moderate",
    "department": "Infectious Disease",
    "admissionDate": "2024-07-20T09:30:00Z",
    "status": "Under Treatment",
    "riskScore": 53,
    "diagnosis": "Bacterial endocarditis",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 4,
    "riskIndicators": "IV drug use history",
    "recentLabResults": [
      "Blood cultures: S. aureus",
      "Echo: Vegetation present",
      "ESR: 95"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 38,
    "name": "Ian Perez",
    "age": 36,
    "conditionSeverity": "Severe",
    "department": "Burns Unit",
    "admissionDate": "2024-07-11T18:15:00Z",
    "status": "Under Treatment",
    "riskScore": 75,
    "diagnosis": "Third-degree burns",
    "treatmentPlanStatus": "Active",
    "medicationCount": 12,
    "riskIndicators": "Infection risk, fluid loss",
    "recentLabResults": [
      "Albumin: 2.3",
      "WBC: 13.2",
      "Burned surface area: 35%"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 39,
    "name": "Violet Roberts",
    "age": 62,
    "conditionSeverity": "Mild",
    "department": "Ophthalmology",
    "admissionDate": "2024-07-27T10:45:00Z",
    "status": "Discharged",
    "riskScore": 21,
    "diagnosis": "Cataract surgery recovery",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "Age-related complications",
    "recentLabResults": [
      "Visual acuity: 20/40",
      "IOP: 14 mmHg",
      "Post-op normal"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 40,
    "name": "Adrian Turner",
    "age": 51,
    "conditionSeverity": "Critical",
    "department": "ICU",
    "admissionDate": "2024-07-02T20:00:00Z",
    "status": "Critical",
    "riskScore": 90,
    "diagnosis": "Drug overdose",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 8,
    "riskIndicators": "Respiratory depression, coma",
    "recentLabResults": [
      "Toxicology: Positive opiates",
      "ABG: pH 7.28",
      "GCS: 8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 41,
    "name": "Kennedy Phillips",
    "age": 30,
    "conditionSeverity": "Moderate",
    "department": "Dermatology",
    "admissionDate": "2024-07-24T13:15:00Z",
    "status": "Under Treatment",
    "riskScore": 39,
    "diagnosis": "Severe psoriasis",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 4,
    "riskIndicators": "Autoimmune condition",
    "recentLabResults": [
      "ESR: 42",
      "Liver function: Normal",
      "Vitamin D: Low"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 42,
    "name": "Jaxon Campbell",
    "age": 66,
    "conditionSeverity": "Severe",
    "department": "Oncology",
    "admissionDate": "2024-07-09T15:45:00Z",
    "status": "Under Treatment",
    "riskScore": 80,
    "diagnosis": "Pancreatic cancer",
    "treatmentPlanStatus": "Active",
    "medicationCount": 9,
    "riskIndicators": "Metastases, poor prognosis",
    "recentLabResults": [
      "CA 19-9: 1850",
      "Bilirubin: 8.2",
      "Albumin: 2.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 43,
    "name": "Penelope Evans",
    "age": 24,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-31T22:30:00Z",
    "status": "Stable",
    "riskScore": 17,
    "diagnosis": "Sprained ankle",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 1,
    "riskIndicators": "None",
    "recentLabResults": [
      "X-ray: No fracture",
      "Swelling moderate",
      "Range of motion limited"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 44,
    "name": "Wyatt Edwards",
    "age": 58,
    "conditionSeverity": "Critical",
    "department": "Cardiac Surgery",
    "admissionDate": "2024-07-01T11:00:00Z",
    "status": "Critical",
    "riskScore": 85,
    "diagnosis": "Post-CABG complications",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 14,
    "riskIndicators": "Graft failure, arrhythmias",
    "recentLabResults": [
      "Troponin: 12.8",
      "Echo: EF 30%",
      "Swan-Ganz: CI 1.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 45,
    "name": "Hazel Collins",
    "age": 47,
    "conditionSeverity": "Moderate",
    "department": "Gynecology",
    "admissionDate": "2024-07-18T16:20:00Z",
    "status": "Under Treatment",
    "riskScore": 43,
    "diagnosis": "Endometriosis",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "Chronic pain, fertility issues",
    "recentLabResults": [
      "CA-125: 85",
      "Hemoglobin: 9.8",
      "MRI: Endometriomas present"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 46,
    "name": "Axel Stewart",
    "age": 41,
    "conditionSeverity": "Severe",
    "department": "Gastroenterology",
    "admissionDate": "2024-07-14T21:45:00Z",
    "status": "Under Treatment",
    "riskScore": 73,
    "diagnosis": "Acute pancreatitis",
    "treatmentPlanStatus": "Active",
    "medicationCount": 8,
    "riskIndicators": "Alcohol history, severe pain",
    "recentLabResults": [
      "Lipase: 2400",
      "Amylase: 680",
      "CT: Pancreatic necrosis"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 47,
    "name": "Nora Sanchez",
    "age": 53,
    "conditionSeverity": "Mild",
    "department": "Internal Medicine",
    "admissionDate": "2024-07-26T08:30:00Z",
    "status": "Stable",
    "riskScore": 26,
    "diagnosis": "Hypertensive crisis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 4,
    "riskIndicators": "Poor medication compliance",
    "recentLabResults": [
      "BP: 210/110 → 140/85",
      "Creatinine: 1.4",
      "Urinalysis: Trace protein"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 48,
    "name": "Easton Morris",
    "age": 32,
    "conditionSeverity": "Critical",
    "department": "Trauma",
    "admissionDate": "2024-07-05T02:15:00Z",
    "status": "Critical",
    "riskScore": 88,
    "diagnosis": "Spinal cord injury",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 11,
    "riskIndicators": "Paralysis, respiratory compromise",
    "recentLabResults": [
      "MRI: C5-C6 injury",
      "Neurological exam: C6 level",
      "Vital capacity: 800 mL"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 49,
    "name": "Ellie Rogers",
    "age": 38,
    "conditionSeverity": "Moderate",
    "department": "Pulmonology",
    "admissionDate": "2024-07-22T14:00:00Z",
    "status": "Under Treatment",
    "riskScore": 49,
    "diagnosis": "Pneumothorax",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 3,
    "riskIndicators": "Smoking history, tall stature",
    "recentLabResults": [
      "Chest X-ray: 40% collapse",
      "ABG: pH 7.42",
      "Chest tube output: 200mL"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 50,
    "name": "Grayson Reed",
    "age": 64,
    "conditionSeverity": "Severe",
    "department": "Vascular Surgery",
    "admissionDate": "2024-07-10T05:30:00Z",
    "status": "Under Treatment",
    "riskScore": 78,
    "diagnosis": "Aortic aneurysm",
    "treatmentPlanStatus": "Active",
    "medicationCount": 7,
    "riskIndicators": "Risk of rupture, hypertension",
    "recentLabResults": [
      "CT angiogram: 6.2 cm",
      "D-Dimer: 1200",
      "Creatinine: 1.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 51,
    "name": "Scarlett Cook",
    "age": 29,
    "conditionSeverity": "Mild",
    "department": "Pediatrics",
    "admissionDate": "2024-07-28T19:15:00Z",
    "status": "Discharged",
    "riskScore": 15,
    "diagnosis": "Viral syndrome",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "None",
    "recentLabResults": [
      "WBC: 5.8",
      "Viral PCR: Rhinovirus",
      "Complete recovery"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 52,
    "name": "Brayden Morgan",
    "age": 60,
    "conditionSeverity": "Critical",
    "department": "ICU",
    "admissionDate": "2024-06-30T17:45:00Z",
    "status": "Critical",
    "riskScore": 92,
    "diagnosis": "Ventilator-associated pneumonia",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 13,
    "riskIndicators": "MRSA infection, prolonged ventilation",
    "recentLabResults": [
      "Sputum culture: MRSA",
      "Procalcitonin: 12.8",
      "PEEP: 15"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 53,
    "name": "Layla Bell",
    "age": 45,
    "conditionSeverity": "Moderate",
    "department": "Rheumatology",
    "admissionDate": "2024-07-19T12:00:00Z",
    "status": "Under Treatment",
    "riskScore": 47,
    "diagnosis": "Rheumatoid arthritis flare",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 6,
    "riskIndicators": "Joint destruction, immunosuppression",
    "recentLabResults": [
      "RF: 180",
      "Anti-CCP: Positive",
      "ESR: 78"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 54,
    "name": "Hudson Murphy",
    "age": 55,
    "conditionSeverity": "Severe",
    "department": "Hepatology",
    "admissionDate": "2024-07-13T20:30:00Z",
    "status": "Under Treatment",
    "riskScore": 81,
    "diagnosis": "Liver cirrhosis with ascites",
    "treatmentPlanStatus": "Active",
    "medicationCount": 10,
    "riskIndicators": "Portal hypertension, varices",
    "recentLabResults": [
      "Bilirubin: 6.4",
      "Albumin: 2.2",
      "INR: 2.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 55,
    "name": "Brooklyn Bailey",
    "age": 31,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-29T23:45:00Z",
    "status": "Stable",
    "riskScore": 23,
    "diagnosis": "Concussion",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 1,
    "riskIndicators": "Head trauma, observation needed",
    "recentLabResults": [
      "CT head: Normal",
      "GCS: 15",
      "Neurological exam: Normal"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 56,
    "name": "Ryder Rivera",
    "age": 72,
    "conditionSeverity": "Critical",
    "department": "Geriatrics",
    "admissionDate": "2024-07-07T08:00:00Z",
    "status": "Critical",
    "riskScore": 94,
    "diagnosis": "Severe delirium with multiple comorbidities",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 15,
    "riskIndicators": "Dementia, polypharmacy, falls risk",
    "recentLabResults": [
      "B12: Low",
      "Folate: Low",
      "TSH: 8.2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 57,
    "name": "Savannah Cooper",
    "age": 26,
    "conditionSeverity": "Moderate",
    "department": "Emergency",
    "admissionDate": "2024-07-25T04:30:00Z",
    "status": "Under Treatment",
    "riskScore": 36,
    "diagnosis": "Ectopic pregnancy",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 3,
    "riskIndicators": "Risk of rupture, bleeding",
    "recentLabResults": [
      "Beta-HCG: 2400",
      "Hemoglobin: 10.2",
      "Pelvic ultrasound: Adnexal mass"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 58,
    "name": "Kai Richardson",
    "age": 50,
    "conditionSeverity": "Severe",
    "department": "Neurology",
    "admissionDate": "2024-07-11T13:15:00Z",
    "status": "Under Treatment",
    "riskScore": 76,
    "diagnosis": "Status epilepticus",
    "treatmentPlanStatus": "Active",
    "medicationCount": 8,
    "riskIndicators": "Refractory seizures, medication toxicity",
    "recentLabResults": [
      "Phenytoin level: 32",
      "EEG: Continuous seizures",
      "Lactate: 6.2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 59,
    "name": "Piper Cox",
    "age": 42,
    "conditionSeverity": "Mild",
    "department": "Family Medicine",
    "admissionDate": "2024-07-27T15:45:00Z",
    "status": "Stable",
    "riskScore": 27,
    "diagnosis": "Acute bronchitis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "Smoking history",
    "recentLabResults": [
      "Chest X-ray: Clear",
      "WBC: 8.9",
      "Sputum: No bacteria"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 60,
    "name": "Theo Ward",
    "age": 67,
    "conditionSeverity": "Critical",
    "department": "Cardiology",
    "admissionDate": "2024-07-04T19:20:00Z",
    "status": "Critical",
    "riskScore": 89,
    "diagnosis": "Ventricular tachycardia",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 12,
    "riskIndicators": "Hemodynamic instability, ICD firing",
    "recentLabResults": [
      "Troponin: 8.4",
      "Magnesium: 1.4",
      "Potassium: 3.2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 61,
    "name": "Ruby Torres",
    "age": 33,
    "conditionSeverity": "Moderate",
    "department": "Infectious Disease",
    "admissionDate": "2024-07-23T10:30:00Z",
    "status": "Under Treatment",
    "riskScore": 51,
    "diagnosis": "Meningitis",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "CNS infection, close contacts",
    "recentLabResults": [
      "CSF: WBC 850, Protein 120",
      "Gram stain: Negative",
      "PCR pending"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 62,
    "name": "Colton Peterson",
    "age": 48,
    "conditionSeverity": "Severe",
    "department": "Orthopedics",
    "admissionDate": "2024-07-16T22:00:00Z",
    "status": "Under Treatment",
    "riskScore": 72,
    "diagnosis": "Complex femur fracture",
    "treatmentPlanStatus": "Active",
    "medicationCount": 7,
    "riskIndicators": "Multiple fractures, fat embolism risk",
    "recentLabResults": [
      "Hemoglobin: 8.8",
      "Platelets: 180",
      "X-ray: Comminuted fracture"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 63,
    "name": "Delilah Gray",
    "age": 39,
    "conditionSeverity": "Mild",
    "department": "Endocrinology",
    "admissionDate": "2024-07-30T09:15:00Z",
    "status": "Stable",
    "riskScore": 29,
    "diagnosis": "Hyperthyroidism",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 2,
    "riskIndicators": "Tachycardia, weight loss",
    "recentLabResults": [
      "TSH: <0.01",
      "Free T4: 4.2",
      "Thyroid scan: Diffuse uptake"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 64,
    "name": "Roman James",
    "age": 56,
    "conditionSeverity": "Critical",
    "department": "ICU",
    "admissionDate": "2024-07-02T14:45:00Z",
    "status": "Critical",
    "riskScore": 91,
    "diagnosis": "Severe COVID-19 pneumonia",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 14,
    "riskIndicators": "ARDS, cytokine storm, immunocompromised",
    "recentLabResults": [
      "COVID PCR: Positive",
      "D-Dimer: 15.2",
      "Ferritin: 2800"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 65,
    "name": "Emery Watson",
    "age": 44,
    "conditionSeverity": "Moderate",
    "department": "Surgery",
    "admissionDate": "2024-07-21T17:30:00Z",
    "status": "Under Treatment",
    "riskScore": 45,
    "diagnosis": "Post-operative ileus",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 4,
    "riskIndicators": "Abdominal surgery, prolonged recovery",
    "recentLabResults": [
      "Abdominal X-ray: Distended loops",
      "Electrolytes: Normal",
      "WBC: 9.8"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 66,
    "name": "Josephine Brooks",
    "age": 61,
    "conditionSeverity": "Severe",
    "department": "Nephrology",
    "admissionDate": "2024-07-12T11:45:00Z",
    "status": "Under Treatment",
    "riskScore": 79,
    "diagnosis": "End-stage renal disease",
    "treatmentPlanStatus": "Active",
    "medicationCount": 11,
    "riskIndicators": "Dialysis dependent, fluid overload",
    "recentLabResults": [
      "Creatinine: 8.4",
      "Potassium: 5.8",
      "Fluid balance: +2.5L"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 67,
    "name": "Maddox Kelly",
    "age": 23,
    "conditionSeverity": "Mild",
    "department": "Emergency",
    "admissionDate": "2024-07-31T01:00:00Z",
    "status": "Discharged",
    "riskScore": 14,
    "diagnosis": "Alcohol intoxication",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 1,
    "riskIndicators": "Substance abuse risk",
    "recentLabResults": [
      "BAC: 0.18",
      "Electrolytes: Normal",
      "Liver function: Normal"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 68,
    "name": "Serenity Sanders",
    "age": 52,
    "conditionSeverity": "Critical",
    "department": "Hematology",
    "admissionDate": "2024-07-08T16:15:00Z",
    "status": "Critical",
    "riskScore": 87,
    "diagnosis": "Thrombotic thrombocytopenic purpura",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 9,
    "riskIndicators": "Microangiopathic hemolytic anemia",
    "recentLabResults": [
      "Platelets: 18,000",
      "LDH: 1200",
      "Schistocytes: 3+"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 69,
    "name": "Jasper Price",
    "age": 37,
    "conditionSeverity": "Moderate",
    "department": "Plastic Surgery",
    "admissionDate": "2024-07-24T13:30:00Z",
    "status": "Under Treatment",
    "riskScore": 40,
    "diagnosis": "Severe burns reconstruction",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 6,
    "riskIndicators": "Infection risk, scarring",
    "recentLabResults": [
      "Wound culture: No growth",
      "Albumin: 3.2",
      "Healing progress: Good"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 70,
    "name": "Luna Bennett",
    "age": 58,
    "conditionSeverity": "Severe",
    "department": "Pulmonology",
    "admissionDate": "2024-07-15T20:45:00Z",
    "status": "Under Treatment",
    "riskScore": 74,
    "diagnosis": "Idiopathic pulmonary fibrosis",
    "treatmentPlanStatus": "Active",
    "medicationCount": 8,
    "riskIndicators": "Progressive disease, hypoxemia",
    "recentLabResults": [
      "HRCT: Honeycombing",
      "PFT: FVC 45% predicted",
      "6MWT: 200 meters"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 71,
    "name": "Declan Wood",
    "age": 46,
    "conditionSeverity": "Mild",
    "department": "Urology",
    "admissionDate": "2024-07-28T11:00:00Z",
    "status": "Stable",
    "riskScore": 25,
    "diagnosis": "Prostatitis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "Chronic condition",
    "recentLabResults": [
      "PSA: 8.2",
      "Urine culture: E. coli",
      "WBC: 12.1"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 72,
    "name": "Gianna Barnes",
    "age": 35,
    "conditionSeverity": "Critical",
    "department": "Obstetrics",
    "admissionDate": "2024-07-06T03:20:00Z",
    "status": "Critical",
    "riskScore": 83,
    "diagnosis": "Postpartum hemorrhage",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 10,
    "riskIndicators": "Massive transfusion, coagulopathy",
    "recentLabResults": [
      "Hemoglobin: 5.2",
      "Platelets: 65,000",
      "INR: 2.4"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 73,
    "name": "Matteo Ross",
    "age": 59,
    "conditionSeverity": "Moderate",
    "department": "Cardiology",
    "admissionDate": "2024-07-17T15:00:00Z",
    "status": "Under Treatment",
    "riskScore": 54,
    "diagnosis": "Stable angina",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 5,
    "riskIndicators": "Diabetes, smoking history",
    "recentLabResults": [
      "Troponin: <0.01",
      "Stress test: Positive",
      "Echo: EF 55%"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 74,
    "name": "Adalynn Henderson",
    "age": 27,
    "conditionSeverity": "Severe",
    "department": "ICU",
    "admissionDate": "2024-07-10T07:30:00Z",
    "status": "Under Treatment",
    "riskScore": 77,
    "diagnosis": "Acute respiratory distress syndrome",
    "treatmentPlanStatus": "Active",
    "medicationCount": 12,
    "riskIndicators": "Prone positioning, high PEEP",
    "recentLabResults": [
      "PaO2/FiO2: 165",
      "Compliance: 28",
      "Driving pressure: 18"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 75,
    "name": "Beckett Coleman",
    "age": 63,
    "conditionSeverity": "Mild",
    "department": "ENT",
    "admissionDate": "2024-07-26T14:15:00Z",
    "status": "Stable",
    "riskScore": 22,
    "diagnosis": "Chronic sinusitis",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 4,
    "riskIndicators": "Allergies, polyps",
    "recentLabResults": [
      "CT sinus: Pansinusitis",
      "WBC: 7.8",
      "IgE: Elevated"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 76,
    "name": "Brielle Jenkins",
    "age": 41,
    "conditionSeverity": "Critical",
    "department": "Neurosurgery",
    "admissionDate": "2024-07-03T12:45:00Z",
    "status": "Critical",
    "riskScore": 86,
    "diagnosis": "Brain tumor",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 8,
    "riskIndicators": "Increased ICP, seizures",
    "recentLabResults": [
      "MRI: 4cm mass",
      "ICP: 22 mmHg",
      "Dexamethasone level: Therapeutic"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 77,
    "name": "Kaden Perry",
    "age": 28,
    "conditionSeverity": "Moderate",
    "department": "Emergency",
    "admissionDate": "2024-07-25T18:20:00Z",
    "status": "Under Treatment",
    "riskScore": 37,
    "diagnosis": "Severe dehydration",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 2,
    "riskIndicators": "Heat exhaustion, electrolyte imbalance",
    "recentLabResults": [
      "Sodium: 148",
      "Creatinine: 1.8",
      "Urine specific gravity: 1.030"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 78,
    "name": "Magnolia Powell",
    "age": 54,
    "conditionSeverity": "Severe",
    "department": "Oncology",
    "admissionDate": "2024-07-14T09:00:00Z",
    "status": "Under Treatment",
    "riskScore": 78,
    "diagnosis": "Lung cancer with metastases",
    "treatmentPlanStatus": "Active",
    "medicationCount": 9,
    "riskIndicators": "Brain metastases, poor performance status",
    "recentLabResults": [
      "CEA: 450",
      "Brain MRI: Multiple lesions",
      "Performance status: 2"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 79,
    "name": "Knox Long",
    "age": 47,
    "conditionSeverity": "Mild",
    "department": "Orthopedics",
    "admissionDate": "2024-07-27T16:45:00Z",
    "status": "Stable",
    "riskScore": 31,
    "diagnosis": "Rotator cuff tear",
    "treatmentPlanStatus": "Stable",
    "medicationCount": 3,
    "riskIndicators": "Age, activity level",
    "recentLabResults": [
      "MRI: Full thickness tear",
      "Range of motion: Limited",
      "Pain score: 6/10"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 80,
    "name": "Paisley Hughes",
    "age": 36,
    "conditionSeverity": "Critical",
    "department": "Emergency",
    "admissionDate": "2024-07-05T21:30:00Z",
    "status": "Critical",
    "riskScore": 85,
    "diagnosis": "Anaphylactic shock",
    "treatmentPlanStatus": "Adjusting",
    "medicationCount": 7,
    "riskIndicators": "Severe allergy, airway compromise",
    "recentLabResults": [
      "Tryptase: 45",
      "BP: 70/40",
      "Peak flow: Unable to perform"
    ],
    "aiAssistedInfo": ""
  },
  {
    "id": 81,
    "name": "Remington Flores",
    "age": 65,
    "conditionSeverity": "Moderate",
    "department": "Geriatrics",
    "admissionDate": "2024-07-20T10:15:00Z",
    "status": "Under Treatment",
    "riskScore": 56,
    "diagnosis": "Falls with multiple injuries",
    "treatmentPlanStatus": "Under Evaluation",
    "medicationCount": 8,
    "riskIndicators": "Polypharmacy, cognitive decline",
    "recentLabResults": [
      "CT head: No hemorrhage",
      "X-ray: Rib fractures",
      "MMSE: 22/30"
    ],
    "aiAssistedInfo": ""
  }
];